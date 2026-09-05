<?php
$current_page = 'social-media/logs.php';
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
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-terminal"></i> Automation Telemetry
            </div>
            <h1 class="adm-hero-title">Social Media Activity Logs</h1>
            <p class="adm-hero-subtitle">Chronological record of automated API dispatch events, connection tokens, and background worker jobs.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="queue.php" class="adm-btn-white me-2">
                <i class="fas fa-list me-2"></i>Post Queue
            </a>
        </div>
    </div>

    <div class="adm-table-container mb-4">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Levels</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                </select>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Platforms</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" placeholder="Search logs..." style="width: 220px;">
                <button class="adm-btn-white text-danger border-danger"><i class="fas fa-trash me-1"></i> Clear Old</button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="adm-table table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Level</th>
                        <th>Platform</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No logs recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style></style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>