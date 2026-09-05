<?php
$current_page = 'social-media/settings.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT 1 FROM sm_connected_accounts LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}

$cronSecretKey = '96e9f6fa819a595ed5f24183a948aa5b';
try {
    $stmtCronKey = $pdo->query("SELECT setting_value FROM sm_settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
    $dbCronKey = $stmtCronKey->fetchColumn();
    if (!empty($dbCronKey)) $cronSecretKey = $dbCronKey;
} catch (\Throwable $e) {}
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-sliders-h"></i> Engine Configuration
            </div>
            <h1 class="adm-hero-title">Social Automation Settings</h1>
            <p class="adm-hero-subtitle">Configure auto-queueing triggers, duplicate spam protection rules, and background worker cron jobs.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="accounts.php" class="adm-btn-white me-2">
                <i class="fas fa-link me-2"></i>Accounts
            </a>
            <a href="queue.php" class="adm-btn-primary">
                <i class="fas fa-list me-2"></i>View Queue
            </a>
        </div>
    </div>

    <div class="adm-card mb-4">
        <div class="p-0">
            <div class="row g-0">
                <div class="col-md-3 border-end">
                        <div class="nav flex-column nav-pills p-3" id="settingsTabs" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active text-start fw-bold mb-2 rounded-pill" data-mdb-toggle="pill" data-mdb-target="#general" type="button" role="tab"><i class="fas fa-cog me-2"></i>General</button>
                            <button class="nav-link text-start fw-bold mb-2 rounded-pill" data-mdb-toggle="pill" data-mdb-target="#protection" type="button" role="tab"><i class="fas fa-shield-alt me-2"></i>Duplicate Protection</button>
                            <button class="nav-link text-start fw-bold mb-2 rounded-pill" data-mdb-toggle="pill" data-mdb-target="#queue" type="button" role="tab"><i class="fas fa-tasks me-2"></i>Queue & Processing</button>
                            <button class="nav-link text-start fw-bold text-danger rounded-pill" data-mdb-toggle="pill" data-mdb-target="#danger" type="button" role="tab"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</button>
                        </div>
                    </div>
                    <div class="col-md-9 p-4">
                        <div class="tab-content" id="settingsTabsContent">
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <h4 class="mb-4">General Settings</h4>
                                <form>
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="autoQueue" checked>
                                            <label class="form-check-label fw-bold" for="autoQueue">Auto-queue New Products</label>
                                            <div class="form-text">Automatically schedule posts for newly added products.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Name Override</label>
                                        <input type="text" class="form-control" placeholder="Default Store Name">
                                    </div>
                                    <button type="submit" class="btn btn-primary mdb-ripple rounded-pill px-4">Save Settings</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="protection" role="tabpanel">
                                <h4 class="mb-4">Duplicate Protection</h4>
                                <p class="text-muted">Settings to prevent spamming platforms with identical posts.</p>
                            </div>
                            <div class="tab-pane fade" id="queue" role="tabpanel">
                                <h4 class="mb-3">Queue & Background Processing</h4>
                                <p class="text-muted small mb-4">Post queue is automatically processed in the background whenever an admin or user is active. For 24/7 standalone posting without active users, configure a Hostinger Cron Job below.</p>
                                
                                <div class="card border-0 bg-light p-4 rounded-4 shadow-sm mb-4">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <i class="fas fa-clock text-warning fs-4"></i>
                                        <h6 class="fw-bold mb-0">Hostinger Cron Job Setup Guide</h6>
                                    </div>
                                    <p class="small text-muted mb-3">Copy either command into Hostinger cPanel -> Cron Jobs (Schedule: Every 5 minutes):</p>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold mb-1">Option 1: PHP CLI Command (Recommended)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control font-monospace bg-white" readonly id="cronCmdCliSettings" value="/usr/bin/php /home/u902894566/domains/sagarstarters.com/public_html/cron/social_media_processor.php">
                                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronCmdCliSettings').value); alert('CLI Command Copied!');"><i class="fas fa-copy me-1"></i>Copy</button>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold mb-1">Option 2: URL / cURL Cron Command</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control font-monospace bg-white" readonly id="cronUrlHttpSettings" value="curl -s -L &quot;<?php echo SITE_URL; ?>/cron/social_media_processor.php?secret=<?php echo htmlspecialchars($cronSecretKey); ?>&quot;">
                                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronUrlHttpSettings').value); alert('URL Cron Command Copied!');"><i class="fas fa-copy me-1"></i>Copy</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="danger" role="tabpanel">
                                <h4 class="mb-4 text-danger">Danger Zone</h4>
                                <div class="alert alert-danger" style="border-radius: 10px;">
                                    <h5 class="fw-bold"><i class="fas fa-exclamation-circle me-2"></i>Clear All Data</h5>
                                    <p>This action will permanently delete all queue items, logs, and analytics. It cannot be undone.</p>
                                    <button class="btn btn-danger mdb-ripple">Clear All Data</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<style>
.nav-pills .nav-link.active { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd !important; }
.nav-pills .nav-link { color: #4f4f4f; }
</style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>