<?php
/**
 * WAPI SaaS - Login Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect(Auth::isAdmin() ? 'admin/' : 'dashboard/');
}

$error = '';
$success = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!CSRF::validateToken()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $auth = new Auth();
            $result = $auth->login($email, $password);

            if ($result['success']) {
                $redirectUrl = $_SESSION['redirect_url'] ?? null;
                unset($_SESSION['redirect_url']);

                if ($redirectUrl) {
                    redirect($redirectUrl);
                } elseif ($result['user']['role'] === 'admin') {
                    redirect('admin/');
                } else {
                    redirect('dashboard/');
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Check for flash messages
$flash = getFlash();
if ($flash) {
    if ($flash['type'] === 'success') $success = $flash['message'];
    else $error = $flash['message'];
}

$pageTitle = 'Login';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card fade-in">
        <div class="auth-logo">
            <div class="brand"><?= e($settings->get('site_name', 'WAPI')); ?></div>
        </div>
        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Sign in to your account to continue</p>

        <div id="alertContainer">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= e($success); ?></div>
            <?php endif; ?>
        </div>

        <form method="POST" action="" id="loginForm">
            <?= CSRF::tokenField(); ?>
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" 
                           value="<?= e($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Password</label>
                    <a href="<?= baseUrl('auth/forgot-password.php'); ?>" style="font-size: 0.8125rem;">Forgot password?</a>
                </div>
                <div class="input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="toggle-password" onclick="togglePassword(this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group d-flex align-items-center gap-2">
                <input type="checkbox" name="remember" id="remember" style="cursor:pointer;">
                <label for="remember" style="font-size: 0.875rem; cursor:pointer; color: var(--text-secondary);">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg" id="loginBtn">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <p class="text-center mt-4" style="font-size: 0.9375rem; color: var(--text-secondary);">
            Don't have an account? <a href="<?= baseUrl('auth/register.php'); ?>" class="fw-bold">Sign Up</a>
        </p>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
