<?php
$current_page = 'social-media/queue.php';
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

$csrfToken = csrf_token();

// 1. Fetch Queue Status Counters
$statusCounts = [
    'all' => 0,
    'pending' => 0,
    'scheduled' => 0,
    'publishing' => 0,
    'posted' => 0,
    'failed' => 0
];

$stmtCounts = $pdo->query("SELECT status, COUNT(*) as c FROM sm_queue GROUP BY status");
while ($row = $stmtCounts->fetch(PDO::FETCH_ASSOC)) {
    $st = strtolower($row['status']);
    $cnt = (int)$row['c'];
    if (isset($statusCounts[$st])) {
        $statusCounts[$st] = $cnt;
    }
    $statusCounts['all'] += $cnt;
}

// 2. Resolve Filters
$currentStatus = strtolower(trim($_GET['status'] ?? 'all'));
$currentPlatform = strtolower(trim($_GET['platform'] ?? ''));
$searchQuery = trim($_GET['search'] ?? '');

$whereClauses = [];
$params = [];

if ($currentStatus !== 'all' && isset($statusCounts[$currentStatus])) {
    $whereClauses[] = "q.status = ?";
    $params[] = $currentStatus;
}

if (!empty($currentPlatform)) {
    $whereClauses[] = "LOWER(q.platform) = ?";
    $params[] = $currentPlatform;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(p.name LIKE ? OR q.post_content LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// 3. Fetch Queue Items
$sql = "SELECT q.*, p.name as product_name, p.image, a.account_name 
        FROM sm_queue q 
        LEFT JOIN products p ON q.product_id = p.id 
        LEFT JOIN sm_connected_accounts a ON q.account_id = a.id 
        {$whereSql} 
        ORDER BY q.scheduled_at ASC, q.id DESC 
        LIMIT 150";

$stmtQueue = $pdo->prepare($sql);
$stmtQueue->execute($params);
$queueItems = $stmtQueue->fetchAll(PDO::FETCH_ASSOC);

// Platform display metadata
$platformIcons = [
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'name' => 'Facebook'],
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'name' => 'Instagram'],
    'twitter' => ['icon' => 'fab fa-x-twitter', 'color' => '#000000', 'name' => 'X (Twitter)'],
    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'name' => 'LinkedIn'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088CC', 'name' => 'Telegram'],
    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#E60023', 'name' => 'Pinterest']
];

$statusBadges = [
    'pending' => ['bg' => 'bg-warning text-dark', 'label' => 'Pending'],
    'scheduled' => ['bg' => 'bg-info text-dark', 'label' => 'Scheduled'],
    'publishing' => ['bg' => 'bg-primary text-white', 'label' => 'Publishing'],
    'posted' => ['bg' => 'bg-success text-white', 'label' => 'Posted'],
    'failed' => ['bg' => 'bg-danger text-white', 'label' => 'Failed']
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold m-0">Queue Management</h2>
            <p class="text-muted small m-0">Monitor, schedule, and execute social media automated posts.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="bulk-schedule.php" class="btn btn-primary rounded-pill shadow-sm">
                <i class="fas fa-plus me-1"></i> Bulk Schedule
            </a>
        </div>
    </div>
    
    <div class="card shadow border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <!-- Filter Tabs -->
            <ul class="nav nav-tabs nav-justified mb-4" id="queueTabs" role="tablist">
                <?php 
                $tabs = [
                    'all' => 'All',
                    'pending' => 'Pending',
                    'scheduled' => 'Scheduled',
                    'publishing' => 'Publishing',
                    'posted' => 'Posted',
                    'failed' => 'Failed'
                ];
                foreach ($tabs as $key => $label):
                    $isActive = ($currentStatus === $key);
                    $count = $statusCounts[$key] ?? 0;
                    $badgeBg = $key === 'all' ? 'bg-secondary' : ($statusBadges[$key]['bg'] ?? 'bg-secondary');
                ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?php echo $isActive ? 'active' : ''; ?>" 
                           href="queue.php?status=<?php echo $key; ?><?php echo !empty($currentPlatform) ? '&platform=' . urlencode($currentPlatform) : ''; ?>">
                            <?php echo $label; ?> 
                            <span class="badge <?php echo $badgeBg; ?> ms-1 rounded-pill"><?php echo $count; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- Controls & Search -->
            <form method="GET" action="queue.php" class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">
                
                <div class="d-flex gap-2 align-items-center">
                    <select id="bulkActionSelect" class="form-select rounded-3" style="width: 180px;">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_post_now">Post Now Selected</option>
                        <option value="bulk_approve">Approve Selected</option>
                        <option value="bulk_cancel">Delete Selected</option>
                        <option value="bulk_retry">Retry Failed</option>
                    </select>
                    <button type="button" id="btnApplyBulk" class="btn btn-outline-primary rounded-3 px-3">Apply</button>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <select name="platform" class="form-select rounded-3" style="width: 160px;" onchange="this.form.submit()">
                        <option value="">All Platforms</option>
                        <?php foreach ($platformIcons as $pKey => $pMeta): ?>
                            <option value="<?php echo $pKey; ?>" <?php echo $currentPlatform === $pKey ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pMeta['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" class="form-control rounded-3" 
                           placeholder="Search product or content..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="width: 220px;">
                    <button type="submit" class="btn btn-light border rounded-3"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <!-- Queue Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" class="form-check-input" id="checkAllQueue"></th>
                            <th>Product</th>
                            <th>Platform & Account</th>
                            <th>Post Preview</th>
                            <th>Status</th>
                            <th>Scheduled At</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queueItems)): ?>
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 text-secondary d-block"></i>
                                    <h5 class="fw-bold">No posts found in the queue</h5>
                                    <p class="small text-muted mb-3">Schedule products in bulk or create auto-posting schedules to populate the queue.</p>
                                    <a href="bulk-schedule.php" class="btn btn-sm btn-primary rounded-pill px-4">
                                        <i class="fas fa-plus me-1"></i> Schedule Products Now
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queueItems as $item): 
                                $pKey = strtolower($item['platform']);
                                $pMeta = $platformIcons[$pKey] ?? ['icon' => 'fas fa-share-alt', 'color' => '#0d6efd', 'name' => ucfirst($pKey)];
                                $stMeta = $statusBadges[strtolower($item['status'])] ?? ['bg' => 'bg-secondary', 'label' => ucfirst($item['status'])];
                                
                                $imgSrc = !empty($item['post_image_url']) ? $item['post_image_url'] : (!empty($item['main_image']) ? SITE_URL . '/' . ltrim($item['main_image'], '/') : SITE_URL . '/assets/images/placeholder.jpg');
                                $prodName = !empty($item['product_name']) ? $item['product_name'] : 'Product #' . $item['product_id'];
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input queue-chk" value="<?php echo $item['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                                 alt="Thumb" class="rounded border object-fit-cover" 
                                                 style="width: 45px; height: 45px;"
                                                 onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.jpg'">
                                            <div>
                                                <div class="fw-bold small text-truncate" style="max-width: 180px;">
                                                    <?php echo htmlspecialchars($prodName); ?>
                                                </div>
                                                <span class="text-muted extra-small">ID: #<?php echo $item['product_id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">
                                            <i class="<?php echo $pMeta['icon']; ?> me-1" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                            <?php echo htmlspecialchars($pMeta['name']); ?>
                                        </span>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars($item['account_name'] ?? 'Default Account'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($item['post_content'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($item['post_content'] ?? 'No content preview'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $stMeta['bg']; ?> rounded-pill px-3 py-2">
                                            <?php echo $stMeta['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold">
                                            <?php echo $item['scheduled_at'] ? date('M d, Y', strtotime($item['scheduled_at'])) : 'Immediate'; ?>
                                        </div>
                                        <div class="extra-small text-muted">
                                            <?php echo $item['scheduled_at'] ? date('h:i A', strtotime($item['scheduled_at'])) : ''; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success btn-post-now" 
                                                    data-id="<?php echo $item['id']; ?>" title="Post Immediately">
                                                <i class="fas fa-bolt"></i> Post Now
                                            </button>
                                            
                                            <?php if ($item['status'] === 'failed'): ?>
                                                <button class="btn btn-outline-warning btn-retry-item" 
                                                        data-id="<?php echo $item['id']; ?>" title="Retry Failed Post">
                                                    <i class="fas fa-redo"></i> Retry
                                                </button>
                                            <?php endif; ?>

                                            <button class="btn btn-outline-danger btn-delete-item" 
                                                    data-id="<?php echo $item['id']; ?>" title="Delete Queue Item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.extra-small { font-size: 0.75rem; }
.nav-tabs .nav-link.active { border-bottom: 3px solid #0d6efd; color: #0d6efd !important; background: transparent; }
.nav-tabs .nav-link { color: #4f4f4f; border: none; padding: 12px 16px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo $csrfToken; ?>';

    // Check All Checkbox
    const checkAll = document.getElementById('checkAllQueue');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('.queue-chk').forEach(c => c.checked = this.checked);
        });
    }

    // Generic Action Handler
    function handleQueueAction(action, id, ids = []) {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('action', action);
        if (id) formData.append('id', id);
        if (ids.length > 0) {
            ids.forEach(i => formData.append('ids[]', i));
        }

        fetch('ajax/ajax_queue_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Action failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Error executing action: ' + err.message));
    }

    // Post Now Button
    document.querySelectorAll('.btn-post-now').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Post this product immediately now?')) {
                handleQueueAction('post_now', this.dataset.id);
            }
        });
    });

    // Retry Button
    document.querySelectorAll('.btn-retry-item').forEach(btn => {
        btn.addEventListener('click', function() {
            handleQueueAction('retry', this.dataset.id);
        });
    });

    // Delete Button
    document.querySelectorAll('.btn-delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this queue item?')) {
                handleQueueAction('delete', this.dataset.id);
            }
        });
    });

    // Apply Bulk Actions
    const btnApplyBulk = document.getElementById('btnApplyBulk');
    if (btnApplyBulk) {
        btnApplyBulk.addEventListener('click', function() {
            const bulkAction = document.getElementById('bulkActionSelect').value;
            if (!bulkAction) {
                alert('Please select a bulk action to apply.');
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.queue-chk:checked')).map(c => c.value);
            if (selectedIds.length === 0) {
                alert('Please select at least one item from the queue.');
                return;
            }

            if (confirm(`Apply ${bulkAction.replace('bulk_', '')} to ${selectedIds.length} selected items?`)) {
                handleQueueAction(bulkAction, null, selectedIds);
            }
        });
    }
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>