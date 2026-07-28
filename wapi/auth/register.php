<?php
/**
 * WAPI SaaS - Registration Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

if (Auth::isLoggedIn()) {
    redirect(Auth::isAdmin() ? 'admin/' : 'dashboard/');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitizeEmail($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate
        $validator = new Validator();
        $isValid = $validator->validate($_POST, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'confirm_password' => 'required|match:password'
        ]);

        if (!$isValid) {
            $error = $validator->getFirstError();
        } else {
            $planSlug = $_POST['plan'] ?? '';
            $auth = new Auth();
            $result = $auth->register($name, $email, $password, $phone, $company, $planSlug);

            if ($result['success']) {
                setFlash('success', 'Registration successful! Please login to continue.');
                redirect('auth/login.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Sign Up';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card fade-in" style="max-width: 500px;">
        <div class="auth-logo">
            <div class="brand"><?= e($settings->get('site_name', 'WAPI')); ?></div>
        </div>
        <h2 class="auth-title">Create Account</h2>
        <p class="auth-subtitle">Start your free trial today. No credit card required.</p>

        <div id="alertContainer">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= e($error); ?></div>
            <?php endif; ?>
        </div>

        <form method="POST" action="" id="registerForm">
            <?= CSRF::tokenField(); ?>
            <input type="hidden" name="plan" value="<?= e($_GET['plan'] ?? ''); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="name" class="form-control" placeholder="John Doe" 
                                   value="<?= e($_POST['name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label class="form-label">Phone Number</label>
                        <div class="input-group">
                            <i class="bi bi-phone input-icon"></i>
                            <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210" 
                                   value="<?= e($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" 
                           value="<?= e($_POST['email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Company Name <span class="text-muted">(optional)</span></label>
                <div class="input-group">
                    <i class="bi bi-building input-icon"></i>
                    <input type="text" name="company" class="form-control" placeholder="Your Company" 
                           value="<?= e($_POST['company'] ?? ''); ?>">
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                            <button type="button" class="toggle-password" onclick="togglePassword(this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group d-flex align-items-start gap-2 mt-3">
                <input type="checkbox" name="terms" id="terms" required style="margin-top: 4px; cursor:pointer;">
                <label for="terms" style="font-size: 0.8125rem; color: var(--text-secondary); cursor:pointer;">
                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-rocket-takeoff"></i> Create Account
            </button>
        </form>

        <p class="text-center mt-4" style="font-size: 0.9375rem; color: var(--text-secondary);">
            Already have an account? <a href="<?= baseUrl('auth/login.php'); ?>" class="fw-bold">Sign In</a>
        </p>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
