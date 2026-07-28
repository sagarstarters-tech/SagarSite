<?php
/**
 * WAPI SaaS - Message Logs
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireActivePlan();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (!CSRF::validateToken()) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $db->query("DELETE FROM messages WHERE user_id = ?", [$userId]);
        setFlash('success', 'All message logs have been cleared successfully.');
    }
    redirect('dashboard/message-logs.php');
}

$hideNav = true; // Prevents landing page nav from appearing in dashboard

$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = 'user_id = ?';
$params = [$userId];

if ($search) {
    $where .= " AND (to_number LIKE ? OR content LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalMessages = $db->count('messages', $where, $params);
$pagination = paginate($totalMessages, $page, 20);
$messages = $db->fetchAll("SELECT m.*, c.name as contact_name FROM messages m LEFT JOIN contacts c ON m.contact_id = c.id WHERE m.{$where} ORDER BY m.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

$pageTitle = 'Message Logs';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Message Logs</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Logs</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-<?= $flash['type'] === 'success' ? 'check' : 'exclamation'; ?>-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">All Messages (<?= $totalMessages; ?>)</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="GET" class="d-flex gap-2 flex-wrap m-0">
                        <div class="search-box"><i class="bi bi-search"></i><input name="search" class="form-control" placeholder="Search..." value="<?= e($search); ?>"></div>
                        <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="queued" <?= $statusFilter === 'queued' ? 'selected' : ''; ?>>Queued</option>
                            <option value="read" <?= $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                        </select>
                    </form>
                    <?php if ($totalMessages > 0): ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('WARNING: This will safely securely clear ALL your message logs! Are you absolutely sure?');">
                        <?= CSRF::tokenField(); ?>
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-danger" style="display: flex; align-items: center; gap: 5px;"><i class="bi bi-trash"></i> Clear Logs</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Direction</th><th>To / From</th><th>Type</th><th>Content</th><th>Status</th><th>Time</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-chat-dots" style="font-size: 2rem;"></i><br>No messages found</td></tr>
                        <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td>
                                <?php if ($msg['direction'] === 'inbound'): ?>
                                <span class="badge-custom" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="bi bi-arrow-down-left"></i> In</span>
                                <?php else: ?>
                                <span class="badge-custom" style="background: rgba(59,130,246,0.1); color: var(--info);"><i class="bi bi-arrow-up-right"></i> Out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($msg['contact_name'] ?? $msg['to_number']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($msg['to_number']); ?></div>
                            </td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($msg['type']); ?></span></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.875rem;"><?= e(substr($msg['content'], 0, 60)); ?><?= strlen($msg['content']) > 60 ? '...' : ''; ?></td>
                            <td><span class="status-badge status-<?= $msg['status']; ?>"><?= ucfirst($msg['status']); ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted); white-space: nowrap;"><?= timeAgo($msg['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-light-primary" onclick="viewMessage(<?= htmlspecialchars(json_encode([
                                    'id'          => $msg['id'],
                                    'direction'   => $msg['direction'],
                                    'to'          => $msg['to_number'],
                                    'name'        => $msg['contact_name'] ?? $msg['to_number'],
                                    'type'        => ucfirst($msg['type']),
                                    'status'      => ucfirst($msg['status']),
                                    'time'        => date('d M Y, H:i:s', strtotime($msg['created_at'])),
                                    'content'     => $msg['content'],
                                    'error'       => $msg['error_message'] ?? '',
                                    'media_url'   => $msg['media_url'] ?? ''
                                ])); ?>)" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&page=%d'); ?></div>
        </div>
    </main>
</div>

<!-- Message Detail Modal -->
<div class="modal fade" id="messageDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" style="color: var(--primary);">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-4 bg-light">
                    <div id="modalIcon" class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.25rem;"></div>
                    <div>
                        <div id="modalTarget" class="fw-bold mb-0"></div>
                        <div id="modalTime" class="text-muted small"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Message Content</label>
                    <div id="modalContent" class="p-3 rounded-3 bg-white border" style="font-size: 0.95rem; white-space: pre-wrap; line-height: 1.6;"></div>
                </div>

                <div class="mb-3" id="modalMediaSection" style="display:none;">
                    <label class="text-muted small fw-bold text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Media URL</label>
                    <a id="modalMediaUrl" href="#" target="_blank" class="d-block text-break small p-2 bg-light rounded border" style="word-break: break-all;"></a>
                </div>

                <div class="mb-3" id="modalErrorSection" style="display:none;">
                    <label class="text-muted small fw-bold text-uppercase mb-2 d-block" style="letter-spacing: 0.5px; color: var(--danger);">❌ Failure Reason (Meta API Error)</label>
                    <div id="modalError" class="p-3 rounded-3 border" style="font-size: 0.85rem; white-space: pre-wrap; line-height: 1.6; background: rgba(239,68,68,0.06); border-color: rgba(239,68,68,0.3) !important; color: #b91c1c;"></div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Type</label>
                        <span id="modalType" class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"></span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Status</label>
                        <span id="modalStatus" class="status-badge"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light w-100" style="border-radius: 10px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-light-primary {
        background: rgba(108, 99, 255, 0.1);
        color: var(--primary);
    }
    .btn-light-primary:hover {
        background: var(--primary);
        color: #fff;
    }
</style>

<script>
    function viewMessage(data) {
        const modal = new bootstrap.Modal(document.getElementById('messageDetailModal'));
        
        // Populate modal data
        document.getElementById('modalTarget').innerText  = data.name;
        document.getElementById('modalTime').innerText    = data.time;
        document.getElementById('modalContent').innerText = data.content || '-';
        document.getElementById('modalType').innerText    = data.type;
        
        const statusEl = document.getElementById('modalStatus');
        statusEl.innerText  = data.status;
        statusEl.className  = 'status-badge status-' + data.status.toLowerCase();
        
        // Media URL
        const mediaSection = document.getElementById('modalMediaSection');
        const mediaLink    = document.getElementById('modalMediaUrl');
        if (data.media_url) {
            mediaLink.href        = data.media_url;
            mediaLink.innerText   = data.media_url;
            mediaSection.style.display = 'block';
        } else {
            mediaSection.style.display = 'none';
        }

        // Error message (only for failed)
        const errorSection = document.getElementById('modalErrorSection');
        const errorBox     = document.getElementById('modalError');
        if (data.error && data.status.toLowerCase() === 'failed') {
            errorBox.innerText = data.error;
            errorSection.style.display = 'block';
        } else {
            errorSection.style.display = 'none';
        }
        
        const iconEl = document.getElementById('modalIcon');
        if (data.direction === 'inbound') {
            iconEl.innerHTML = '<i class="bi bi-arrow-down-left"></i>';
            iconEl.style.background = 'rgba(16, 185, 129, 0.1)';
            iconEl.style.color = 'var(--success)';
        } else {
            iconEl.innerHTML = '<i class="bi bi-arrow-up-right"></i>';
            iconEl.style.background = 'rgba(59, 130, 246, 0.1)';
            iconEl.style.color = 'var(--info)';
        }

        modal.show();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
