<?php
$current_page = 'social-media/settings.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Module Settings</h2>
        <div class="card shadow" style="border-radius: 15px; border: none;">
            <div class="card-body p-0">
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
                                <h4 class="mb-4">Queue & Processing</h4>
                                <p class="text-muted">Cron and retry settings.</p>
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
</div>
<style>
.nav-pills .nav-link.active { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd !important; }
.nav-pills .nav-link { color: #4f4f4f; }
</style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>