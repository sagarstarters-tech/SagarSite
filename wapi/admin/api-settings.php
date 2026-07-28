<?php
/**
 * WAPI SaaS - Admin WhatsApp API Settings
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $fields = [
        'whatsapp_api_url', 'whatsapp_api_version', 'webhook_verify_token',
        'whatsapp_app_id', 'whatsapp_app_secret'
    ];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $settings->set($field, sanitize($_POST[$field]));
        }
    }
    setFlash('success', 'WhatsApp API settings saved successfully!');
    redirect('admin/api-settings.php');
}

$pageTitle = 'WhatsApp API Settings';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">WhatsApp API Settings</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>WhatsApp API</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= CSRF::tokenField(); ?>

            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-whatsapp text-success"></i> Meta WhatsApp Cloud API</h5>
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label">API Base URL</label>
                            <input type="text" name="whatsapp_api_url" class="form-control" value="<?= e($settings->get('whatsapp_api_url', 'https://graph.facebook.com')); ?>">
                            <small class="text-muted">Default: https://graph.facebook.com</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">API Version</label>
                            <select name="whatsapp_api_version" class="form-control">
                                <?php foreach (['v21.0','v20.0','v19.0','v18.0','v17.0'] as $v): ?>
                                <option value="<?= $v; ?>" <?= $settings->get('whatsapp_api_version', 'v17.0') === $v ? 'selected' : ''; ?>><?= $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook App ID</label>
                            <input type="text" name="whatsapp_app_id" class="form-control" value="<?= e($settings->get('whatsapp_app_id', '')); ?>" placeholder="Enter your Facebook App ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook App Secret</label>
                            <input type="password" name="whatsapp_app_secret" class="form-control" value="<?= e($settings->get('whatsapp_app_secret', '')); ?>" placeholder="Enter your App Secret">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-link-45deg text-primary"></i> Webhook Configuration</h5>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">Webhook URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= APP_URL; ?>/api/webhook.php" readonly style="background: var(--bg-secondary);">
                                <button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText('<?= APP_URL; ?>/api/webhook.php'); this.innerHTML='<i class=\'bi bi-check\'></i> Copied!';">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            <small class="text-muted">Use this URL in your Meta Developer Console webhook settings</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Verify Token</label>
                            <input type="text" name="webhook_verify_token" class="form-control" value="<?= e($settings->get('webhook_verify_token', WEBHOOK_VERIFY_TOKEN)); ?>">
                            <small class="text-muted">Use this same token in Meta Developer Console</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-info-circle text-info"></i> Setup Guide</h5>
                    <ol style="font-size: 0.9375rem; line-height: 2;">
                        <li>Go to <a href="https://developers.facebook.com" target="_blank">Meta Developer Console</a> and create an app</li>
                        <li>Add WhatsApp product to your app</li>
                        <li>Go to WhatsApp → Getting Started to get your <strong>Phone Number ID</strong> and <strong>Temporary Access Token</strong></li>
                        <li>Set up Webhook with the URL and Verify Token shown above</li>
                        <li>Subscribe to message events: <code>messages</code>, <code>message_deliveries</code>, <code>message_reads</code></li>
                        <li>Generate a <strong>Permanent Access Token</strong> via System Users</li>
                        <li>Each user configures their own Phone Number ID and Access Token in Dashboard → Settings</li>
                    </ol>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
        </form>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
