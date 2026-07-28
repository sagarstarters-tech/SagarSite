<?php
/**
 * WAPI SaaS - Email Verification Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$token = sanitize($_GET['token'] ?? '');
$message = '';
$status = 'info';

if (!empty($token)) {
    $auth = new Auth();
    $result = $auth->verifyEmail($token);
    
    if ($result['success']) {
        setFlash('success', $result['message']);
        redirect(Auth::isLoggedIn() ? 'dashboard/' : 'auth/login.php');
    } else {
        $message = $result['message'];
        $status = 'danger';
    }
} else {
    // If logged in but not verified, resend email
    if (Auth::isLoggedIn()) {
        $user = (new Auth())->getCurrentUser();
        if ($user && $user['email_verified']) {
            redirect('dashboard/');
        } else {
            $auth = new Auth();
            $result = $auth->resendVerificationEmail($_SESSION['user_id']);
            if ($result['success']) {
                $status = 'info';
                setFlash('success', $result['message']);
            } else {
                $status = 'danger';
                $message = $result['message'];
            }
        }
    } else {
        redirect('auth/login.php');
    }
}

$pageTitle = 'Email Verification';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card fade-in" style="max-width: 500px; text-align: center;">
        <div class="auth-logo">
            <div class="brand"><?= e($settings->get('site_name', 'WAPI')); ?></div>
        </div>
        
        <?php if ($status === 'danger'): ?>
            <div class="mb-4">
                <i class="bi bi-exclamation-octagon text-danger" style="font-size: 4rem;"></i>
            </div>
            <h2 class="auth-title">Verification Failed</h2>
            <p class="text-muted mb-4"><?= e($message); ?></p>
            <a href="<?= baseUrl('dashboard/'); ?>" class="btn btn-primary w-100">Go to Dashboard</a>
        <?php else: ?>
            <div class="mb-4">
                <i class="bi bi-envelope-check text-primary" style="font-size: 4rem;"></i>
            </div>
            <h2 class="auth-title">Check Your Email</h2>
            <p class="text-muted mb-4">We've sent a verification link to your email address. Please click the link in the email to verify your account.</p>
            
            <div class="alert alert-info py-2" style="font-size: 0.875rem;">
                <i class="bi bi-info-circle-fill"></i> Didn't receive the email? Check your spam folder or contact support.
            </div>
            
            <a href="<?= baseUrl('dashboard/'); ?>" class="btn btn-primary w-100">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
