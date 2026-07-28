<?php
/**
 * WAPI SaaS - Reset Password Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

if (Auth::isLoggedIn()) {
    redirect('dashboard/');
}

$token = sanitize($_GET['token'] ?? '');

if (empty($token)) {
    setFlash('danger', 'Invalid or missing password reset token.');
    redirect('auth/login.php');
}

$db = Database::getInstance();
$user = $db->fetch("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()", [$token]);

if (!$user) {
    setFlash('danger', 'Invalid or expired password reset token. Please request a new one.');
    redirect('auth/forgot-password.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $error = 'Invalid security token.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $auth = new Auth();
            $result = $auth->resetPassword($token, $password);
            
            if ($result['success']) {
                setFlash('success', 'Your password has been reset successfully! You can now log in.');
                redirect('auth/login.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card fade-in">
        <div class="auth-logo">
            <div class="brand"><?= e($settings->get('site_name', 'WAPI')); ?></div>
        </div>
        <h2 class="auth-title">Reset Password</h2>
        <p class="auth-subtitle">Enter your new password below.</p>

        <div id="alertContainer">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= e($error); ?></div>
            <?php endif; ?>
        </div>

        <form method="POST" action="">
            <?= CSRF::tokenField(); ?>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required minlength="8" autofocus>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <i class="bi bi-shield-lock input-icon"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Type password again" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-check-circle"></i> Reset Password
            </button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
