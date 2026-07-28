<?php
/**
 * WAPI SaaS - Forgot Password Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

if (Auth::isLoggedIn()) {
    redirect('dashboard/');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $error = 'Invalid security token.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            $auth = new Auth();
            $result = $auth->forgotPassword($email);
            if (!empty($result['success']) && $result['success'] === true) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card fade-in">
        <div class="auth-logo">
            <div class="brand"><?= e($settings->get('site_name', 'WAPI')); ?></div>
        </div>
        <h2 class="auth-title">Forgot Password?</h2>
        <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

        <div id="alertContainer">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= e($success); ?></div>
            <?php endif; ?>
        </div>

        <form method="POST" action="">
            <?= CSRF::tokenField(); ?>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-send"></i> Send Reset Link
            </button>
        </form>

        <p class="text-center mt-4" style="font-size: 0.9375rem; color: var(--text-secondary);">
            Remember your password? <a href="<?= baseUrl('auth/login.php'); ?>" class="fw-bold">Sign In</a>
        </p>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
