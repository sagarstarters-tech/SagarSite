<?php
/**
 * WAPI SaaS - Admin Messages Overview
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = '1';
$params = [];

if ($search) {
    $where .= " AND (m.to_number LIKE ? OR m.content LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($statusFilter) {
    $where .= " AND m.status = ?";
    $params[] = $statusFilter;
}

$totalMessages = $db->fetchColumn("SELECT COUNT(*) FROM messages m JOIN users u ON m.user_id = u.id WHERE {$where}", $params);
$pagination = paginate($totalMessages, $page, 20);
$messages = $db->fetchAll("SELECT m.*, u.name as user_name, u.email as user_email FROM messages m JOIN users u ON m.user_id = u.id WHERE {$where} ORDER BY m.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

$pageTitle = 'Messages';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Messages</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Messages</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">All Messages (<?= $totalMessages; ?>)</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <div class="search-box"><i class="bi bi-search"></i><input name="search" class="form-control" placeholder="Search..." value="<?= e($search); ?>"></div>
                    <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="queued" <?= $statusFilter === 'queued' ? 'selected' : ''; ?>>Queued</option>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>User</th><th>Direction</th><th>To / From</th><th>Type</th><th>Content</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No messages found</td></tr>
                        <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold" style="font-size: 0.875rem;"><?= e($msg['user_name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($msg['user_email']); ?></div>
                            </td>
                            <td>
                                <?php if ($msg['direction'] === 'inbound'): ?>
                                <span class="badge-custom" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="bi bi-arrow-down-left"></i> In</span>
                                <?php else: ?>
                                <span class="badge-custom" style="background: rgba(59,130,246,0.1); color: var(--info);"><i class="bi bi-arrow-up-right"></i> Out</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.875rem;"><?= e($msg['to_number']); ?></td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($msg['type']); ?></span></td>
                            <td style="max-width: 200px; font-size: 0.8125rem;" class="text-truncate"><?= e(substr($msg['content'], 0, 50)); ?></td>
                            <td><span class="status-badge status-<?= $msg['status']; ?>"><?= ucfirst($msg['status']); ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= timeAgo($msg['created_at']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&page=%d'); ?></div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
