<?php
include 'admin_header.php';
require_once BASE_PATH . '/includes/RateLimiter.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: index.php");
    exit;
}

$limiter = new RateLimiter(5, 900); // 5 attempts per 15 min

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($limiter->isBlocked()) {
        $mins = ceil($limiter->getRemainingLockSeconds() / 60);
        $error = "Too many failed login attempts. Please try again in {$mins} minute(s).";
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $limiter->reset();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
                    $stmt->close();
                    header("Location: index.php");
                    exit;
                }
            }
            $stmt->close();
        }
        $limiter->recordFailure();
        $left = $limiter->getAttemptsLeft();
        $error = "Invalid admin credentials!" . ($left > 0 ? " ({$left} attempt(s) remaining)" : " Account temporarily locked.");
    }
}
?>

<div class="auth-page-bg" style="min-height: 100vh;">
    <div class="container py-3">
        <div class="auth-master-card">
            <div class="row g-0">
                <!-- ════════ LEFT PANE: VISUAL HERO ARTWORK ════════ -->
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="auth-hero-pane">
                        <div class="auth-hero-content">
                            <div class="auth-brand-badge">
                                <i class="fas fa-bolt"></i> <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Sagar Starters'); ?>
                            </div>
                            <h2 class="auth-hero-title">Smart Power &amp; Motor Control</h2>
                            <p class="auth-hero-desc">
                                Centralized Management Console for inventory, live orders, customer relations, and business analytics.
                            </p>

                            <div class="auth-feature-pills">
                                <div class="auth-feature-pill">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Enterprise-Grade Security &amp; Protection</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-truck-fast"></i>
                                    <span>Live Order Processing &amp; Logistics</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-headset"></i>
                                    <span>24/7 Expert Technical Support</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-hero-footer">
                            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Sagar Starters'); ?></span>
                            <div class="auth-social-icons">
                                <?php if(!empty($global_settings['social_facebook'])): ?>
                                    <a href="<?php echo htmlspecialchars($global_settings['social_facebook']); ?>" target="_blank" rel="noopener noreferrer" class="auth-social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <?php endif; ?>
                                <?php if(!empty($global_settings['social_instagram'])): ?>
                                    <a href="<?php echo htmlspecialchars($global_settings['social_instagram']); ?>" target="_blank" rel="noopener noreferrer" class="auth-social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                                <?php endif; ?>
                                <?php if(!empty($global_settings['social_whatsapp'])): ?>
                                    <a href="<?php echo htmlspecialchars($global_settings['social_whatsapp']); ?>" target="_blank" rel="noopener noreferrer" class="auth-social-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                <?php endif; ?>
                                <?php if(!empty($global_settings['social_youtube'])): ?>
                                    <a href="<?php echo htmlspecialchars($global_settings['social_youtube']); ?>" target="_blank" rel="noopener noreferrer" class="auth-social-btn" title="YouTube"><i class="fab fa-youtube"></i></a>
                                <?php endif; ?>
                                <?php if(!empty($global_settings['social_twitter'])): ?>
                                    <a href="<?php echo htmlspecialchars($global_settings['social_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="auth-social-btn" title="Twitter"><i class="fab fa-twitter"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════ RIGHT PANE: LOGIN FORM ════════ -->
                <div class="col-lg-7">
                    <div class="auth-form-pane">
                        <!-- Mobile Brand Header (< 992px) -->
                        <div class="d-lg-none text-center mb-3">
                            <span class="auth-brand-badge mb-2 d-inline-flex bg-dark text-warning">
                                <i class="fas fa-bolt text-warning"></i> <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Sagar Starters'); ?>
                            </span>
                        </div>

                        <!-- Top Switcher Tab -->
                        <div class="auth-tab-nav">
                            <a href="admin_login.php" class="auth-tab-link active">Sign In</a>
                            <a href="<?php echo store_url('user/login.php'); ?>" class="auth-tab-link">Store Login</a>
                        </div>

                        <div class="auth-form-header">
                            <h3>Welcome Back! 👋</h3>
                            <p>Enter your admin credentials to access the control dashboard.</p>
                        </div>

                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
                                <i class="fas fa-exclamation-circle text-danger flex-shrink-0"></i>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="on">
                            <?php echo csrf_input(); ?>

                            <!-- Email Field -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="admin_email">Email Address</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-envelope auth-input-icon"></i>
                                    <input type="email" name="email" id="admin_email" class="auth-input-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email" required>
                                </div>
                            </div>

                            <!-- Password Field -->
                            <div class="auth-field-wrapper">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="auth-field-label mb-0" for="admin_password">Password</label>
                                </div>
                                <div class="auth-input-container">
                                    <i class="fas fa-lock auth-input-icon"></i>
                                    <input type="password" name="password" id="admin_password" class="auth-input-control pe-5" placeholder="Enter your password" autocomplete="current-password" required>
                                    <button type="button" class="auth-pw-toggle-btn" onclick="togglePasswordVisibility('admin_password', this)" title="Show/Hide Password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-auth-submit">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In to Dashboard
                            </button>
                        </form>

                        <!-- Back to Store Link -->
                        <div class="text-center mt-4">
                            <a href="<?php echo store_url('index.php'); ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1">
                                <i class="fas fa-arrow-left me-1"></i> Back to Store
                            </a>
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

<?php include 'admin_footer.php'; ?>
