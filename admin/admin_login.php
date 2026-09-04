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

<style>
/* ═══════════════════════════════════════════════════════════
   PREMIUM SPLIT-CARD AUTHENTICATION (ADMIN PORTAL)
   ═══════════════════════════════════════════════════════════ */
.auth-page-bg {
    background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 50%, #e2e8f0 100%) !important;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    font-family: 'Montserrat', 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
}

.auth-master-card {
    background: #ffffff !important;
    border-radius: 24px !important;
    box-shadow: 0 20px 60px rgba(10, 37, 64, 0.12), 0 4px 20px rgba(0, 0, 0, 0.04) !important;
    overflow: hidden;
    max-width: 1050px;
    width: 100%;
    margin: 0 auto;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Left Pane: Visual Hero Artwork */
.auth-hero-pane {
    position: relative;
    background: #0a2540 url('<?php echo ASSETS_URL; ?>/images/auth_banner.jpg') center/cover no-repeat !important;
    color: #ffffff !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 3rem 2.5rem;
    overflow: hidden;
    height: 100%;
    min-height: 580px;
}

.auth-hero-pane::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(10, 37, 64, 0.75) 0%, rgba(6, 78, 59, 0.88) 100%) !important;
    z-index: 1;
}

.auth-hero-content {
    position: relative;
    z-index: 2;
}

.auth-brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.14) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    padding: 0.4rem 0.95rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #ffd166 !important;
}

.auth-hero-title {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.25;
    margin-top: 1.5rem;
    margin-bottom: 0.85rem;
    color: #ffffff !important;
}

.auth-hero-desc {
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.88) !important;
    margin-bottom: 2rem;
}

.auth-feature-pills {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.auth-feature-pill {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.14) !important;
    border-radius: 12px;
    padding: 0.65rem 1rem;
    font-size: 0.88rem;
    color: #ffffff !important;
    font-weight: 500;
}

.auth-feature-pill i {
    color: #06d6a0 !important;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.auth-hero-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding-top: 1.2rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.75) !important;
    position: relative;
    z-index: 2;
}

.auth-social-icons {
    display: flex;
    gap: 8px;
}

.auth-social-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff !important;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    text-decoration: none;
}

.auth-social-btn:hover {
    background: #ffffff !important;
    color: #0a2540 !important;
    transform: translateY(-2px);
}

/* Right Pane: Form Area */
.auth-form-pane {
    padding: 3rem 2.8rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #ffffff !important;
    height: 100%;
}

/* Tab Switcher */
.auth-tab-nav {
    display: flex;
    background: #f1f5f9 !important;
    padding: 4px;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.auth-tab-link {
    flex: 1;
    text-align: center;
    padding: 0.6rem 1rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.9rem;
    color: #64748b !important;
    text-decoration: none;
    transition: all 0.25s ease;
}

.auth-tab-link.active {
    background: #0a2540 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(10, 37, 64, 0.15) !important;
}

.auth-tab-link:hover:not(.active) {
    color: #0a2540 !important;
    background: rgba(255, 255, 255, 0.6) !important;
}

.auth-form-header h3 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #0f172a !important;
    margin-bottom: 0.35rem;
}

.auth-form-header p {
    font-size: 0.9rem;
    color: #64748b !important;
    margin-bottom: 1.8rem;
}

/* Form Fields */
.auth-field-wrapper {
    position: relative;
    margin-bottom: 1.25rem;
}

.auth-field-wrapper .auth-field-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #475569 !important;
    margin-bottom: 0.4rem;
}

.auth-input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.auth-input-icon {
    position: absolute;
    left: 1.1rem;
    color: #94a3b8 !important;
    font-size: 1rem;
    pointer-events: none;
    transition: color 0.2s ease;
    z-index: 3;
}

.auth-input-control {
    width: 100% !important;
    padding: 0.75rem 1rem 0.75rem 2.8rem !important;
    font-size: 0.93rem !important;
    border-radius: 12px !important;
    border: 1.5px solid #e2e8f0 !important;
    background-color: #f8fafc !important;
    color: #1e293b !important;
    transition: all 0.25s ease !important;
    box-sizing: border-box !important;
}

.auth-input-control:focus {
    background-color: #ffffff !important;
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.12) !important;
    outline: none !important;
}

.auth-input-container:focus-within .auth-input-icon {
    color: #0d6efd !important;
}

.auth-pw-toggle-btn {
    position: absolute;
    right: 1rem;
    background: transparent !important;
    border: none !important;
    color: #94a3b8 !important;
    cursor: pointer;
    font-size: 1rem;
    padding: 0.25rem;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.auth-pw-toggle-btn:hover {
    color: #334155 !important;
}

/* Action Submit Button */
.btn-auth-submit {
    background: linear-gradient(135deg, #0a2540 0%, #008080 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 0.85rem 1.5rem !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    width: 100% !important;
    box-shadow: 0 8px 20px rgba(10, 37, 64, 0.2) !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    margin-top: 0.5rem;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
}

.btn-auth-submit:hover {
    background: linear-gradient(135deg, #0f365d 0%, #009688 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(10, 37, 64, 0.25) !important;
    color: #ffffff !important;
}

.btn-auth-submit:active {
    transform: translateY(0);
}

/* Responsive */
@media (max-width: 991.98px) {
    .auth-page-bg {
        padding: 1.5rem 0.75rem;
    }
    .auth-master-card {
        border-radius: 20px !important;
        max-width: 600px;
    }
    .auth-form-pane {
        padding: 2.2rem 1.8rem;
    }
}

@media (max-width: 575.98px) {
    .auth-page-bg {
        padding: 0.75rem 0.4rem;
        background: #ffffff !important;
    }
    .auth-master-card {
        border-radius: 16px !important;
        box-shadow: none !important;
        border: none !important;
    }
    .auth-form-pane {
        padding: 1rem 0.35rem;
    }
    .auth-form-header h3 {
        font-size: 1.45rem;
    }
}
</style>

<div class="auth-page-bg">
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
                            <a href="admin_login.php" class="auth-tab-link active">Admin Sign In</a>
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
