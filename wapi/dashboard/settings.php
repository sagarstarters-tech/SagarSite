<?php
/**
 * WAPI SaaS - User Account Settings
 * WhatsApp API configuration, profile, password change
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? LIMIT 1", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone'] ?? '');
        $company = sanitize($_POST['company'] ?? '');

        $db->update('users', ['name' => $name, 'phone' => $phone, 'company_name' => $company], 'id = ?', [$userId]);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profile updated.');
    } elseif ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            setFlash('danger', 'Current password is incorrect.');
        } elseif (strlen($newPassword) < 8) {
            setFlash('danger', 'New password must be at least 8 characters.');
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('danger', 'Passwords do not match.');
        } else {
            $db->update('users', ['password' => password_hash($newPassword, PASSWORD_BCRYPT)], 'id = ?', [$userId]);
            setFlash('success', 'Password changed successfully.');
        }
    }
    redirect('dashboard/settings.php?tab=' . sanitize($_POST['tab'] ?? 'profile'));
}

$activeTab = sanitize($_GET['tab'] ?? 'profile');

$pageTitle = 'Settings';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Account Settings</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Settings</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-<?= $flash['type'] === 'success' ? 'check' : 'exclamation'; ?>-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4 flex-wrap gap-2">
            <li><a class="nav-link <?= $activeTab === 'profile' ? 'active' : ''; ?> btn-sm" href="?tab=profile" style="border-radius: 8px;">Profile</a></li>
            <li><a class="nav-link <?= $activeTab === 'password' ? 'active' : ''; ?> btn-sm" href="?tab=password" style="border-radius: 8px;">Password</a></li>
        </ul>

        <div class="card" style="border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <?php if ($activeTab === 'profile'): ?>
                <h5 class="fw-bold mb-4">Profile Information</h5>
                <form method="POST">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="profile">
                    <input type="hidden" name="tab" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= e($user['name']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= e($user['email']); ?>" disabled><small class="text-muted">Email cannot be changed</small></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? ''); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company" class="form-control" value="<?= e($user['company_name'] ?? ''); ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-check-lg"></i> Save Changes</button>
                </form>

                <?php elseif ($activeTab === 'password'): ?>
                <h5 class="fw-bold mb-4">Change Password</h5>
                <form method="POST">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="password">
                    <input type="hidden" name="tab" value="password">
                    <div class="row g-3" style="max-width: 500px;">
                        <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
                        <div class="col-12"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-lock"></i> Update Password</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
