<?php
/**
 * WAPI SaaS - Admin Templates Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

$templates = $db->fetchAll("SELECT t.*, u.name as user_name FROM templates t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");

$pageTitle = 'Message Templates';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Message Templates</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Templates</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">All Templates (<?= count($templates); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Name</th><th>User</th><th>Category</th><th>Language</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No templates found</td></tr>
                        <?php else: ?>
                        <?php foreach ($templates as $t): ?>
                        <tr>
                            <td class="fw-bold"><?= e($t['name']); ?></td>
                            <td style="font-size: 0.875rem;"><?= e($t['user_name']); ?></td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($t['category']); ?></span></td>
                            <td><?= e($t['language']); ?></td>
                            <td><span class="status-badge status-<?= $t['status'] === 'approved' ? 'active' : ($t['status'] === 'rejected' ? 'failed' : 'pending'); ?>"><?= ucfirst($t['status']); ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= formatDate($t['created_at']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
