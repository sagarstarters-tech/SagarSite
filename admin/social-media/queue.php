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

// Reset stuck 'publishing' items older than 1 minute back to 'scheduled'
try {
    $pdo->query("UPDATE sm_queue SET status = 'scheduled' WHERE status = 'publishing' AND (updated_at <= NOW() - INTERVAL 1 MINUTE OR updated_at IS NULL)");
} catch (Exception $e) {}

// Auto-process any due scheduled posts automatically on page load
try {
    require_once __DIR__ . '/services/QueueProcessor.php';
    $autoProcessor = new \Admin\SocialMedia\Services\QueueProcessor();
    $autoProcessor->processBatch(10);
} catch (Exception $e) {}

// 1. Fetch Queue Status Counters
$statusCounts = [
    'all' => 0,
    'pending' => 0,
    'scheduled' => 0,
    'publishing' => 0,
    'posted' => 0,
    'failed' => 0
];

// Fix platform case inconsistencies first (normalize to lowercase)
try {
    $pdo->query("UPDATE sm_queue SET platform = LOWER(platform) WHERE platform != LOWER(platform)");
} catch (Exception $e) {}

// Clean existing duplicate queued items — keep the NEWEST entry per product + platform combination
try {
    $pdo->query("DELETE q1 FROM sm_queue q1
        INNER JOIN sm_queue q2 
        ON q1.product_id = q2.product_id 
        AND LOWER(q1.platform) = LOWER(q2.platform) 
        AND q1.id < q2.id
        WHERE q1.status IN ('pending', 'scheduled')
          AND q2.status IN ('pending', 'scheduled')");
} catch (Exception $e) {}

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
    'pending' => ['bg' => 'bg-warning text-dark border border-warning fw-bold', 'label' => 'Pending'],
    'scheduled' => ['bg' => 'bg-primary text-white border border-primary fw-bold', 'label' => 'Scheduled'],
    'publishing' => ['bg' => 'bg-info text-white border border-info fw-bold', 'label' => 'Publishing'],
    'posted' => ['bg' => 'bg-success text-white border border-success fw-bold', 'label' => 'Posted'],
    'failed' => ['bg' => 'bg-danger text-white border border-danger fw-bold', 'label' => 'Failed']
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
            <ul class="nav nav-tabs nav-justified queue-tabs-wrapper mb-4" id="queueTabs" role="tablist">
                <?php 
                $tabs = [
                    'all' => ['label' => 'ALL', 'badge' => 'badge-all'],
                    'pending' => ['label' => 'PENDING', 'badge' => 'badge-pending'],
                    'scheduled' => ['label' => 'SCHEDULED', 'badge' => 'badge-scheduled'],
                    'publishing' => ['label' => 'PUBLISHING', 'badge' => 'badge-publishing'],
                    'posted' => ['label' => 'POSTED', 'badge' => 'badge-posted'],
                    'failed' => ['label' => 'FAILED', 'badge' => 'badge-failed']
                ];
                foreach ($tabs as $key => $tData):
                    $isActive = ($currentStatus === $key);
                    $count = $statusCounts[$key] ?? 0;
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" 
                           href="queue.php?status=<?php echo $key; ?><?php echo !empty($currentPlatform) ? '&platform=' . urlencode($currentPlatform) : ''; ?>">
                            <?php echo $tData['label']; ?> 
                            <span class="status-pill-badge <?php echo $tData['badge']; ?> ms-2"><?php echo $count; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- Controls & Search -->
            <form method="GET" action="queue.php" class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">
                
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select id="bulkActionSelect" class="form-select rounded-3" style="width: 180px;">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_post_now">Post Now Selected</option>
                        <option value="bulk_approve">Approve Selected</option>
                        <option value="bulk_cancel">Delete Selected</option>
                        <option value="bulk_retry">Retry Failed</option>
                    </select>
                    <button type="button" id="btnApplyBulk" class="btn btn-outline-primary rounded-3 px-3">Apply</button>
                    <button type="button" id="btnDeleteSelected" class="btn btn-danger rounded-3 px-3">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <button type="button" id="btnDeleteAllQueue" class="btn btn-outline-danger rounded-3 px-3">
                        <i class="fas fa-trash me-1"></i> Delete All
                    </button>
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
                                
                                $rawPath = trim($item['post_image_url'] ?: ($item['image'] ?? ''));
                                if (empty($rawPath)) {
                                    $imgSrc = SITE_URL . '/assets/images/logo.jpg';
                                } elseif (strpos($rawPath, 'http://') === 0 || strpos($rawPath, 'https://') === 0) {
                                    $imgSrc = $rawPath;
                                } elseif (strpos($rawPath, 'uploads/') === 0 || strpos($rawPath, 'assets/') === 0) {
                                    $imgSrc = SITE_URL . '/' . ltrim($rawPath, '/');
                                } else {
                                    $imgSrc = SITE_URL . '/uploads/media/images/' . ltrim($rawPath, '/');
                                }

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
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/assets/images/logo.jpg';">
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
                                        <?php 
                                        $displayTime = !empty($item['published_at']) ? $item['published_at'] : (!empty($item['scheduled_at']) ? $item['scheduled_at'] : null);
                                        $isPosted = strtolower($item['status']) === 'posted';
                                        ?>
                                        <div class="small fw-semibold <?php echo $isPosted ? 'text-success' : 'text-dark'; ?>">
                                            <?php echo $displayTime ? date('M d, Y', strtotime($displayTime)) : 'Immediate'; ?>
                                        </div>
                                        <div class="extra-small text-muted">
                                            <?php echo $displayTime ? date('h:i A', strtotime($displayTime)) : ''; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <button type="button" class="btn btn-sm btn-success px-2 py-1 btn-post-now text-white fw-bold shadow-sm" 
                                                    data-id="<?php echo $item['id']; ?>" title="Post Immediately">
                                                <i class="fas fa-bolt me-1"></i> Post Now
                                            </button>
                                            
                                            <?php if (strtolower($item['status']) === 'failed'): ?>
                                                <button type="button" class="btn btn-sm btn-warning px-2 py-1 btn-retry-item text-dark fw-bold shadow-sm" 
                                                        data-id="<?php echo $item['id']; ?>" title="Retry Failed Post">
                                                    <i class="fas fa-redo me-1"></i> Retry
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-sm btn-danger px-2 py-1 btn-delete-item text-white shadow-sm" 
                                                    data-id="<?php echo $item['id']; ?>" title="Delete Queue Item">
                                                <i class="fas fa-trash-alt"></i>
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
.queue-tabs-wrapper {
    border-bottom: 2px solid #e9ecef;
}
.queue-tabs-wrapper .nav-link {
    font-size: 0.9rem;
    font-weight: 700;
    color: #495057 !important;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 18px;
    transition: all 0.2s ease;
    letter-spacing: 0.5px;
}
.queue-tabs-wrapper .nav-link:hover {
    color: #0d6efd !important;
    background: rgba(13, 110, 253, 0.04);
    border-radius: 8px 8px 0 0;
}
.queue-tabs-wrapper .nav-link.active {
    border-bottom: 3px solid #0d6efd !important;
    color: #0d6efd !important;
    background: rgba(13, 110, 253, 0.08);
    border-radius: 8px 8px 0 0;
}
.status-pill-badge {
    display: inline-block;
    padding: 4px 10px;
    font-size: 0.82rem;
    font-weight: 800;
    border-radius: 50rem;
    line-height: 1;
    min-width: 28px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.status-pill-badge.badge-all { background-color: #495057; color: #ffffff !important; }
.status-pill-badge.badge-pending { background-color: #ffc107; color: #000000 !important; }
.status-pill-badge.badge-scheduled { background-color: #0dcaf0; color: #000000 !important; }
.status-pill-badge.badge-publishing { background-color: #0d6efd; color: #ffffff !important; }
.status-pill-badge.badge-posted { background-color: #198754; color: #ffffff !important; }
.status-pill-badge.badge-failed { background-color: #dc3545; color: #ffffff !important; }
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
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (confirm('Post this product immediately now?')) {
                handleQueueAction('post_now', this.dataset.id);
            }
        });
    });

    // Retry Button
    document.querySelectorAll('.btn-retry-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleQueueAction('retry', this.dataset.id);
        });
    });

    // Delete Button
    document.querySelectorAll('.btn-delete-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this queue item?')) {
                handleQueueAction('delete', this.dataset.id);
            }
        });
    });

    // Apply Bulk Actions
    const btnApplyBulk = document.getElementById('btnApplyBulk');
    if (btnApplyBulk) {
        btnApplyBulk.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            let bulkAction = document.getElementById('bulkActionSelect').value;
            const selectedIds = Array.from(document.querySelectorAll('.queue-chk:checked')).map(c => c.value);

            if (selectedIds.length === 0) {
                alert('Please select at least one item from the queue.');
                return;
            }

            // If no dropdown action selected, default to Delete Selected
            if (!bulkAction) {
                bulkAction = 'bulk_delete';
                const sel = document.getElementById('bulkActionSelect');
                if (sel) sel.value = 'bulk_cancel';
            }

            const actionLabel = bulkAction.replace('bulk_', '').replace('_', ' ');
            if (confirm(`Apply '${actionLabel}' to ${selectedIds.length} selected item(s)?`)) {
                handleQueueAction(bulkAction, null, selectedIds);
            }
        });
    }

    // Delete Selected Button
    const btnDeleteSelected = document.getElementById('btnDeleteSelected');
    if (btnDeleteSelected) {
        btnDeleteSelected.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const selectedIds = Array.from(document.querySelectorAll('.queue-chk:checked')).map(c => c.value);
            if (selectedIds.length === 0) {
                alert('Please select at least one item from the queue to delete.');
                return;
            }

            const sel = document.getElementById('bulkActionSelect');
            if (sel) sel.value = 'bulk_cancel';

            if (confirm(`Are you sure you want to delete ${selectedIds.length} selected item(s)?`)) {
                handleQueueAction('bulk_delete', null, selectedIds);
            }
        });
    }

    // Delete All Button
    const btnDeleteAllQueue = document.getElementById('btnDeleteAllQueue');
    if (btnDeleteAllQueue) {
        btnDeleteAllQueue.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStatus = '<?php echo $currentStatus; ?>';
            const confirmMsg = currentStatus === 'all' 
                ? 'Are you SURE you want to delete ALL items in the entire queue?' 
                : `Are you SURE you want to delete ALL '${currentStatus}' items from the queue?`;
            
            if (confirm(confirmMsg)) {
                const formData = new FormData();
                formData.append('_csrf_token', csrfToken);
                formData.append('action', 'delete_all');
                formData.append('status', currentStatus);

                fetch('ajax/ajax_queue_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Delete all failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error executing action: ' + err.message));
            }
        });
    }

    // Background Auto-Runner: Automatically process due posts every 60 seconds
    setInterval(function() {
        fetch('ajax/ajax_process_queue.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.processed > 0 && data.succeeded > 0) {
                    console.log(`Auto-processed ${data.succeeded} due social media posts!`);
                    window.location.reload();
                }
            })
            .catch(err => console.error('Auto-runner ping error:', err));
    }, 60000);
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>