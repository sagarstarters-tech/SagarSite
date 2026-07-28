<?php
/**
 * WAPI SaaS - Admin Email / SMTP Settings
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = sanitize($_POST['action'] ?? 'save');

    if ($action === 'save') {
        $fields = ['email_driver','smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password','smtp_from_name','smtp_from_email'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $settings->set($f, sanitize($_POST[$f]));
        }
        setFlash('success', 'Email settings saved!');
    } elseif ($action === 'test') {
        $testEmail = sanitizeEmail($_POST['test_email'] ?? '');
        if ($testEmail) {
            $subject = 'WAPI - SMTP Test Email';
            $body = 'This is a test email from your WAPI platform. If you received this, your SMTP configuration is working correctly!';
            
            $result = Mail::send($testEmail, $subject, $body);
            
            if ($result['success']) {
                setFlash('success', "Test email sent to {$testEmail} via SMTP!");
            } else {
                setFlash('danger', $result['message']);
            }
        }
    }
    redirect('admin/email-settings.php');
}

$pageTitle = 'Email / SMTP Settings';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Email / SMTP Settings</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Email</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-<?= $flash['type'] === 'success' ? 'check' : 'exclamation'; ?>-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4"><i class="bi bi-envelope text-primary"></i> SMTP Configuration</h5>
                        <form method="POST">
                            <?= CSRF::tokenField(); ?>
                            <input type="hidden" name="action" value="save">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Mail Driver</label>
                                    <select name="email_driver" class="form-control">
                                        <option value="mail" <?= $settings->get('email_driver', 'mail') === 'mail' ? 'selected' : ''; ?>>PHP Mail (Previous/Default)</option>
                                        <option value="smtp" <?= $settings->get('email_driver') === 'smtp' ? 'selected' : ''; ?>>SMTP (Gmail, Hostinger, etc.)</option>
                                    </select>
                                    <small class="text-muted">Choose "PHP Mail" to use your server's default mailer (Sendmail).</small>
                                </div>
                                <div class="col-md-8"><label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= e($settings->get('smtp_host', '')); ?>" placeholder="smtp.gmail.com"></div>
                                <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= e($settings->get('smtp_port', '587')); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Encryption</label>
                                    <select name="smtp_encryption" class="form-control">
                                        <option value="tls" <?= $settings->get('smtp_encryption', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?= $settings->get('smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?= $settings->get('smtp_encryption') === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Username</label><input type="text" name="smtp_username" class="form-control" value="<?= e($settings->get('smtp_username', '')); ?>"></div>
                                <div class="col-12"><label class="form-label">Password</label><input type="password" name="smtp_password" class="form-control" value="<?= e($settings->get('smtp_password', '')); ?>"></div>
                                <div class="col-md-6"><label class="form-label">From Name</label><input type="text" name="smtp_from_name" class="form-control" value="<?= e($settings->get('smtp_from_name', '')); ?>" placeholder="WAPI Platform"></div>
                                <div class="col-md-6"><label class="form-label">From Email</label><input type="email" name="smtp_from_email" class="form-control" value="<?= e($settings->get('smtp_from_email', '')); ?>" placeholder="noreply@yourdomain.com"></div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-check-lg"></i> Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-send-check text-success"></i> Send Test Email</h5>
                        <form method="POST">
                            <?= CSRF::tokenField(); ?>
                            <input type="hidden" name="action" value="test">
                            <div class="form-group">
                                <label class="form-label">Recipient Email</label>
                                <input type="email" name="test_email" class="form-control" placeholder="test@example.com" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-send"></i> Send Test</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning"></i> Common SMTP Settings</h5>
                        <div style="font-size: 0.8125rem;">
                            <div class="mb-3">
                                <strong>Gmail:</strong><br>
                                Host: smtp.gmail.com<br>
                                Port: 587 | Encryption: TLS<br>
                                <small class="text-danger"><i class="bi bi-info-circle"></i> Use "App Password" if 2FA is enabled.</small>
                            </div>
                            <div class="mb-3">
                                <strong>Hostinger:</strong><br>
                                Host: smtp.hostinger.com<br>
                                Port: 465 | Encryption: SSL
                            </div>
                            <div>
                                <strong>SendGrid:</strong><br>
                                Host: smtp.sendgrid.net<br>
                                Port: 587 | Encryption: TLS
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
