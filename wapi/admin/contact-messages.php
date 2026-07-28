<?php
/**
 * WAPI SaaS - Admin Contact Messages
 * View and manage messages submitted via the Contact Us form.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true;

// ---- Handle status update (AJAX) ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $id = sanitizeInt($_POST['id'] ?? 0);

    if ($_POST['action'] === 'update_status') {
        $newStatus = sanitize($_POST['status'] ?? '');
        if (!in_array($newStatus, ['unread', 'read', 'replied'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit;
        }
        $db->update('contact_messages', ['status' => $newStatus], 'id = ?', [$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $db->delete('contact_messages', 'id = ?', [$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ---- Ensure table exists ---------------------------------------------------
$db->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('unread','read','replied') DEFAULT 'unread',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add phone column if it doesn't exist (for existing tables)
try {
    $db->query("ALTER TABLE `contact_messages` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `email`");
} catch (Exception $e) {
    // Column already exists — ignore
}

// ---- Filters & Pagination --------------------------------------------------
$search       = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page         = max(1, sanitizeInt($_GET['page'] ?? 1));

$where  = '1';
$params = [];

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalMessages = $db->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE {$where}", $params);
$pagination    = paginate($totalMessages, $page, 20);
$messages      = $db->fetchAll(
    "SELECT * FROM contact_messages WHERE {$where} ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$unreadCount = $db->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");

$pageTitle = 'Contact Messages';
$extraCss  = [asset('assets/css/dashboard.css')];
$extraJs   = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Contact Messages</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('admin/'); ?>">Admin</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Contact Messages</span>
                </div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">
                    All Messages (<?= $totalMessages; ?>)
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $unreadCount; ?> unread</span>
                    <?php endif; ?>
                </h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <div class="search-box"><i class="bi bi-search"></i><input name="search" class="form-control" placeholder="Search..." value="<?= e($search); ?>"></div>
                    <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                        <option value="read" <?= $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="replied" <?= $statusFilter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No contact messages found</td></tr>
                        <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr id="row-<?= $msg['id']; ?>" class="<?= $msg['status'] === 'unread' ? 'table-warning' : ''; ?>">
                            <td>
                                <div class="fw-semibold" style="font-size: 0.875rem;"><?= e($msg['first_name'] . ' ' . $msg['last_name']); ?></div>
                            </td>
                            <td style="font-size: 0.875rem;"><?= e($msg['email']); ?></td>
                            <td style="font-size: 0.875rem;"><?= e($msg['phone'] ?? '—'); ?></td>
                            <td style="font-size: 0.875rem;"><?= e($msg['subject']); ?></td>
                            <td style="max-width: 220px; font-size: 0.8125rem;" class="text-truncate" title="<?= e($msg['message']); ?>"><?= e(substr($msg['message'], 0, 60)); ?></td>
                            <td>
                                <select class="form-select form-select-sm status-select" data-id="<?= $msg['id']; ?>" style="width: 110px;">
                                    <option value="unread" <?= $msg['status'] === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                    <option value="read" <?= $msg['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                    <option value="replied" <?= $msg['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                </select>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= timeAgo($msg['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1 view-msg-btn"
                                    data-name="<?= e($msg['first_name'] . ' ' . $msg['last_name']); ?>"
                                    data-email="<?= e($msg['email']); ?>"
                                    data-phone="<?= e($msg['phone'] ?? ''); ?>"
                                    data-subject="<?= e($msg['subject']); ?>"
                                    data-message="<?= e($msg['message']); ?>"
                                    data-time="<?= e($msg['created_at']); ?>"
                                    data-ip="<?= e($msg['ip_address']); ?>"
                                    title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-msg-btn" data-id="<?= $msg['id']; ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
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

<!-- View Message Modal -->
<div class="modal fade" id="viewMsgModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope-open me-2"></i>Message Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>Name:</strong> <span id="mdName"></span></p>
        <p><strong>Email:</strong> <span id="mdEmail"></span></p>
        <p><strong>Phone:</strong> <span id="mdPhone"></span></p>
        <p><strong>Subject:</strong> <span id="mdSubject"></span></p>
        <p><strong>Sent at:</strong> <span id="mdTime"></span></p>
        <p><strong>IP:</strong> <span id="mdIp"></span></p>
        <hr>
        <div id="mdMessage" style="white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer">
        <a href="#" id="mdReplyLink" class="btn btn-primary"><i class="bi bi-reply me-1"></i>Reply via Email</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUrl = window.location.pathname + window.location.search;

    // ---- View message modal ----
    document.querySelectorAll('.view-msg-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('mdName').textContent    = btn.dataset.name;
            document.getElementById('mdEmail').textContent   = btn.dataset.email;
            document.getElementById('mdPhone').textContent   = btn.dataset.phone || '—';
            document.getElementById('mdSubject').textContent = btn.dataset.subject;
            document.getElementById('mdMessage').textContent = btn.dataset.message;
            document.getElementById('mdTime').textContent    = btn.dataset.time;
            document.getElementById('mdIp').textContent      = btn.dataset.ip || '—';
            document.getElementById('mdReplyLink').href      = 'mailto:' + btn.dataset.email + '?subject=Re: ' + encodeURIComponent(btn.dataset.subject);
            new bootstrap.Modal(document.getElementById('viewMsgModal')).show();
        });
    });

    // ---- Status change (inline) ----
    document.querySelectorAll('.status-select').forEach(sel => {
        sel.addEventListener('change', function() {
            const id     = this.dataset.id;
            const status = this.value;
            const fd     = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('status', status);

            fetch(currentUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const row = document.getElementById('row-' + id);
                        row.classList.toggle('table-warning', status === 'unread');
                    }
                });
        });
    });

    // ---- Delete ----
    document.querySelectorAll('.delete-msg-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this message?')) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch(currentUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('row-' + id).remove();
                    }
                });
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
