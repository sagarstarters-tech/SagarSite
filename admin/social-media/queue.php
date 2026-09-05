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

$cronSecretKey = '96e9f6fa819a595ed5f24183a948aa5b';
try {
    $stmtCronKey = $pdo->query("SELECT setting_value FROM sm_settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
    $dbCronKey = $stmtCronKey->fetchColumn();
    if (!empty($dbCronKey)) $cronSecretKey = $dbCronKey;
} catch (\Throwable $e) {}

// Automatically purge scheduled/pending queue items for deleted products
try {
    $pdo->query("DELETE FROM sm_queue WHERE product_id IS NOT NULL AND product_id > 0 AND product_id NOT IN (SELECT id FROM products) AND status IN ('pending', 'scheduled', 'retry')");
} catch (\Throwable $e) {}

// Fast GET Page Load: Fetch Queue Status Counters without blocking network API calls
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
        ORDER BY q.scheduled_at ASC, q.id ASC 
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
    'retry' => ['bg' => 'bg-warning text-dark border border-warning fw-bold', 'label' => 'Retry Scheduled'],
    'failed' => ['bg' => 'bg-danger text-white border border-danger fw-bold', 'label' => 'Failed']
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<style>
/* Search & Refresh Buttons High Contrast Styles */
.queue-btn-search {
    background-color: #2563eb !important;
    border: 1px solid #2563eb !important;
    color: #ffffff !important;
    height: 38px !important;
    min-width: 42px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.2) !important;
    cursor: pointer !important;
}
.queue-btn-search i {
    color: #ffffff !important;
    font-size: 0.95rem !important;
}
.queue-btn-search:hover {
    background-color: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.35) !important;
    transform: translateY(-1px) !important;
}

.queue-btn-refresh {
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #1e293b !important;
    font-weight: 600 !important;
    font-size: 0.875rem !important;
    height: 38px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
    cursor: pointer !important;
    white-space: nowrap !important;
}
.queue-btn-refresh i {
    color: #2563eb !important;
    font-size: 0.95rem !important;
    transition: transform 0.3s ease !important;
}
.queue-btn-refresh:hover {
    background-color: #eff6ff !important;
    border-color: #3b82f6 !important;
    color: #1d4ed8 !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.18) !important;
    transform: translateY(-1px) !important;
}
.queue-btn-refresh:hover i {
    color: #1d4ed8 !important;
}
.queue-timer-badge {
    background-color: #eff6ff !important;
    color: #2563eb !important;
    border: 1px solid #bfdbfe !important;
    padding: 2px 7px !important;
    border-radius: 6px !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    font-family: monospace !important;
    line-height: 1.2 !important;
    display: inline-block !important;
}
</style>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-layer-group"></i> Dispatch Pipeline
            </div>
            <h1 class="adm-hero-title">Social Media Queue Management</h1>
            <p class="adm-hero-subtitle">Monitor scheduled social posts, retry failed dispatches, preview product copy, and execute instant publishing.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="bulk-schedule.php" class="adm-btn-white me-2">
                <i class="fas fa-plus me-2"></i>Bulk Schedule
            </a>
            <button class="adm-btn-primary" id="btnManualProcessQueue" type="button">
                <i class="fas fa-paper-plane me-2"></i>Process Due Posts Now
            </button>
        </div>
    </div>
    
    <!-- Meta Rate Limit Protection Notice -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 p-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-shield-alt text-info fs-5"></i>
            <div>
                <strong class="text-dark">Meta Rate Limit Protection:</strong>
                <span class="small text-muted d-block">If Facebook or Instagram shows <em>"We limit how often you can post"</em> or <em>"User is performing too many actions"</em>, Meta has applied a temporary anti-spam block (usually 24 hours). 💡 <strong>Quick Unblock Tip:</strong> Open <a href="https://facebook.com" target="_blank" class="text-primary fw-bold">facebook.com</a> in your browser, go to your Page <em>"Sagar starter's"</em>, and publish 1 post manually to clear Facebook's security flag immediately.</span>
            </div>
        </div>
    </div>

    <!-- Automated Background Processing Banner (with Anti-Backlog Guard) -->
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-grow spinner-grow-sm text-success" role="status"></span>
                <strong class="text-success">Auto Background Posting Active:</strong>
                <span class="small text-dark">Freshly scheduled & on-time posts publish automatically in the background. 🛡️ <em>Anti-Backlog Guard</em> blocks old expired posts (>30m) from mass auto-firing.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" id="btnManualProcessQueue" type="button">
                    <i class="fas fa-paper-plane me-1"></i> Process Due Posts Now
                </button>
                <button class="btn btn-sm btn-outline-success rounded-pill px-3" type="button" data-mdb-toggle="collapse" data-bs-toggle="collapse" data-mdb-target="#hostingerCronInfo" data-bs-target="#hostingerCronInfo" aria-expanded="false">
                    <i class="fas fa-clock me-1"></i> Hostinger Cron Guide
                </button>
            </div>
        </div>
        <div class="collapse mt-3" id="hostingerCronInfo">
            <div class="card card-body bg-white border-0 rounded-3 shadow-sm">
                <h6 class="fw-bold mb-2"><i class="fas fa-server text-primary me-2"></i>Hostinger Server Cron Job (Optional - 24/7 posting even if 0 visitors online)</h6>
                <p class="small text-muted mb-3">Add this command in Hostinger cPanel -> Cron Jobs (Schedule: Every 5 minutes):</p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Option 1: PHP CLI Command (Recommended)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace bg-light" readonly id="cronCmdCliSocial" value="/usr/bin/php /home/u902894566/domains/sagarstarters.com/public_html/cron/social_media_processor.php">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronCmdCliSocial').value); alert('CLI Command Copied!');"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Option 2: URL / cURL Cron Command</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace bg-light" readonly id="cronUrlHttpSocial" value="curl -s -L &quot;<?php echo SITE_URL; ?>/cron/social_media_processor.php?secret=<?php echo htmlspecialchars($cronSecretKey); ?>&quot;">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronUrlHttpSocial').value); alert('URL Cron Command Copied!');"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                </div>
            </div>
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
                    <button type="button" id="btnApplyBulk" class="btn btn-primary rounded-3 px-3 fw-semibold">Apply</button>
                    <button type="button" id="btnDeleteSelected" class="btn btn-outline-danger rounded-3 px-3 fw-semibold">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <button type="button" id="btnDeleteAllQueue" class="btn btn-danger rounded-3 px-3 fw-semibold shadow-sm">
                        <i class="fas fa-trash me-1"></i> Delete All
                    </button>
                    <?php
                    $activeScheduleCount = (int)$pdo->query("SELECT COUNT(*) FROM sm_schedules WHERE is_active = 1")->fetchColumn();
                    $isPostingStopped = ($activeScheduleCount === 0);
                    ?>
                    <button type="button" id="btnTogglePosting" 
                        class="btn <?php echo $isPostingStopped ? 'btn-success' : 'btn-dark'; ?> rounded-3 px-3"
                        data-posting-state="<?php echo $isPostingStopped ? 'stopped' : 'active'; ?>">
                        <i class="fas <?php echo $isPostingStopped ? 'fa-play-circle' : 'fa-stop-circle'; ?> me-1"></i>
                        <?php echo $isPostingStopped ? 'Start Posting' : 'Stop Posting'; ?>
                    </button>
                    <?php
                    // Show reset button only if there are stuck publishing items
                    $stuckCount = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE status='publishing' AND updated_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
                    if ($stuckCount > 0):
                    ?>
                    <button type="button" id="btnResetStuck" class="btn btn-warning rounded-3 px-3">
                        <i class="fas fa-sync-alt me-1"></i> Reset <?php echo $stuckCount; ?> Stuck Post(s)
                    </button>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select name="platform" class="form-select rounded-3" style="width: 160px;" onchange="this.form.submit()">
                        <option value="">All Platforms</option>
                        <?php foreach ($platformIcons as $pKey => $pMeta): ?>
                            <option value="<?php echo $pKey; ?>" <?php echo $currentPlatform === $pKey ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pMeta['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" id="queueSearchInput" class="form-control rounded-3" 
                           placeholder="Search product or content..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="width: 220px;">
                    <button type="submit" class="btn queue-btn-search rounded-3" title="Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <button type="button" class="btn queue-btn-refresh rounded-3 shadow-sm" onclick="safelyRefreshQueuePage(true)" title="Refresh Queue (Auto-refreshes every 30s)">
                        <i class="fas fa-sync-alt" id="queueRefreshIcon"></i>
                        <span class="d-none d-sm-inline">Refresh</span>
                        <span id="queueAutoRefreshTimer" class="queue-timer-badge">30s</span>
                    </button>
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
                                
                                // Resolve image fresh from product (matches product page behavior)
                                // Don't rely on post_image_url stored at queue creation — it may be stale/wrong
                                if (function_exists('resolve_product_image_url')) {
                                    $imgSrc = resolve_product_image_url('', $conn ?? null, (int)$item['product_id']);
                                } else {
                                    $rawPath = trim($item['image'] ?? $item['post_image_url'] ?? '');
                                    $rawPath = str_replace('/uploads/media/images/', '/uploads/images/', $rawPath);
                                    if (empty($rawPath)) {
                                        $imgSrc = (defined('ASSETS_URL') ? ASSETS_URL : SITE_URL . '/assets') . '/images/logo.jpg';
                                    } elseif (strpos($rawPath, 'http://') === 0 || strpos($rawPath, 'https://') === 0) {
                                        $imgSrc = $rawPath;
                                    } elseif (strpos($rawPath, 'uploads/') === 0 || strpos($rawPath, 'assets/') === 0) {
                                        $imgSrc = SITE_URL . '/' . ltrim($rawPath, '/');
                                    } else {
                                        $imgSrc = SITE_URL . '/assets/images/' . ltrim($rawPath, '/');
                                    }
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
                                                 onerror="this.onerror=null; this.src='<?php echo defined('ASSETS_URL') ? ASSETS_URL : SITE_URL . '/assets'; ?>/images/logo.jpg';">
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
                                        <span class="badge <?php echo $stMeta['bg']; ?> rounded-pill px-3 py-2" title="<?php echo htmlspecialchars($item['last_error'] ?? ''); ?>">
                                            <?php echo $stMeta['label']; ?>
                                        </span>
                                        <?php if (!empty($item['last_error']) && strtolower($item['status']) !== 'posted'): ?>
                                            <div class="text-danger mt-1" style="font-size: 11px; max-width: 160px; line-height: 1.2;" title="<?php echo htmlspecialchars($item['last_error']); ?>">
                                                <i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($item['last_error']); ?>
                                            </div>
                                        <?php endif; ?>
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
                                     <td class="text-end" style="white-space: nowrap;">
                                         <div class="d-flex justify-content-end align-items-center gap-2 flex-nowrap">
                                             <?php if (strtolower($item['status']) === 'posted'): 
                                                 $postUrl = '#';
                                                 $pPostId = trim($item['platform_post_id'] ?? '');

                                                 if (!empty($pPostId)) {
                                                     if (strpos($pPostId, 'http://') === 0 || strpos($pPostId, 'https://') === 0) {
                                                         $postUrl = $pPostId;
                                                     } else {
                                                         switch ($pKey) {
                                                             case 'facebook':
                                                                 $parts = explode('_', $pPostId);
                                                                 if (count($parts) === 2) {
                                                                     $postUrl = "https://facebook.com/{$parts[0]}/posts/{$parts[1]}";
                                                                 } else {
                                                                     $postUrl = "https://facebook.com/{$pPostId}";
                                                                 }
                                                                 break;
                                                             case 'linkedin':
                                                                 $postUrl = "https://www.linkedin.com/feed/update/{$pPostId}";
                                                                 break;
                                                             case 'instagram':
                                                                if (is_numeric($pPostId) && (function_exists('gmp_init') || function_exists('bcdiv'))) {
                                                                    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
                                                                    $sc = '';
                                                                    $idVal = $pPostId;
                                                                    if (function_exists('gmp_init')) {
                                                                        $gmp = gmp_init($idVal, 10);
                                                                        while (gmp_cmp($gmp, 0) > 0) {
                                                                            $rem = gmp_intval(gmp_mod($gmp, 64));
                                                                            $sc = $alphabet[$rem] . $sc;
                                                                            $gmp = gmp_div_q($gmp, 64);
                                                                        }
                                                                    } elseif (function_exists('bcdiv')) {
                                                                        while (bccomp($idVal, '0') > 0) {
                                                                            $rem = (int)bcmod($idVal, '64');
                                                                            $sc = $alphabet[$rem] . $sc;
                                                                            $idVal = bcdiv($idVal, '64', 0);
                                                                        }
                                                                    }
                                                                    $postUrl = "https://www.instagram.com/p/" . (!empty($sc) ? $sc : $pPostId) . "/";
                                                                } else {
                                                                    $postUrl = "https://www.instagram.com/p/{$pPostId}/";
                                                                }
                                                                break;
                                                             case 'twitter':
                                                             case 'x':
                                                                 $postUrl = "https://x.com/i/web/status/{$pPostId}";
                                                                 break;
                                                             case 'telegram':
                                                                 $postUrl = "https://t.me/{$pPostId}";
                                                                 break;
                                                             case 'pinterest':
                                                                 $postUrl = "https://www.pinterest.com/pin/{$pPostId}/";
                                                                 break;
                                                             default:
                                                                 $postUrl = "https://facebook.com/{$pPostId}";
                                                                 break;
                                                         }
                                                     }
                                                 } else if ($pKey === 'facebook') {
                                                     $postUrl = 'https://www.facebook.com';
                                                 } else if ($pKey === 'linkedin') {
                                                     $postUrl = 'https://www.linkedin.com';
                                                 } else if ($pKey === 'instagram') {
                                                     $postUrl = 'https://www.instagram.com';
                                                 }

                                                 if ($postUrl !== '#'):
                                             ?>
                                                 <a href="<?php echo htmlspecialchars($postUrl); ?>" target="_blank" 
                                                    class="btn btn-sm btn-primary rounded-pill shadow-sm d-inline-flex align-items-center gap-1 text-white fw-semibold btn-view-post" 
                                                    style="font-size: 11px; padding: 4px 10px; white-space: nowrap; text-transform: none; text-decoration: none;" 
                                                    title="View Published Post">
                                                     <i class="fas fa-external-link-alt"></i> View Post
                                                 </a>
                                             <?php endif; endif; ?>

                                             <button type="button" class="btn btn-sm btn-success rounded-pill btn-post-now text-white fw-bold shadow-sm d-inline-flex align-items-center gap-1" 
                                                     style="font-size: 11px; padding: 4px 10px; white-space: nowrap; text-transform: none;" 
                                                     data-id="<?php echo $item['id']; ?>" title="Post Immediately">
                                                 <i class="fas fa-bolt"></i> Post Now
                                             </button>
                                             
                                             <?php if (in_array(strtolower($item['status']), ['failed', 'retry'])): ?>
                                                 <button type="button" class="btn btn-sm btn-warning rounded-pill btn-retry-item text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-1" 
                                                         style="font-size: 11px; padding: 4px 10px; white-space: nowrap; text-transform: none;" 
                                                         data-id="<?php echo $item['id']; ?>" title="Retry Failed Post">
                                                     <i class="fas fa-redo"></i> Retry
                                                 </button>
                                             <?php endif; ?>

                                             <button type="button" class="btn btn-sm btn-danger rounded-circle btn-delete-item shadow-sm d-inline-flex align-items-center justify-content-center" 
                                                     style="width: 30px; height: 30px; padding: 0; min-width: 30px; flex-shrink: 0;" 
                                                     data-id="<?php echo $item['id']; ?>" title="Delete Queue Item">
                                                 <i class="fas fa-trash" style="font-size: 12px; color: #ffffff !important;"></i>
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
.btn-delete-item {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: #ffffff !important;
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    border-radius: 50% !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3) !important;
}
.btn-delete-item i {
    font-size: 12px !important;
    color: #ffffff !important;
    display: inline-block !important;
}
.btn-delete-item:hover {
    background-color: #bb2d3b !important;
    border-color: #b02a37 !important;
    transform: scale(1.05);
}
.btn-view-post {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    padding: 4px 10px !important;
    border-radius: 50rem !important;
    text-transform: none !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    box-shadow: 0 2px 5px rgba(13, 110, 253, 0.25) !important;
}
.btn-view-post:hover {
    background-color: #0b5ed7 !important;
    color: #ffffff !important;
}
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
                ? '⚠️ PERMANENT DELETION:\n\nAre you sure you want to COMPLETELY & PERMANENTLY DELETE ALL posts from the queue and database?\n\nThis will wipe out all scheduled and pending posts completely.' 
                : `⚠️ PERMANENT DELETION:\n\nAre you sure you want to COMPLETELY & PERMANENTLY DELETE ALL '${currentStatus}' posts from the queue and database?`;
            
            if (confirm(confirmMsg)) {
                btnDeleteAllQueue.disabled = true;
                btnDeleteAllQueue.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Deleting...';

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
                        window.location.href = 'queue.php';
                    } else {
                        alert('Delete all failed: ' + (data.error || 'Unknown error'));
                        btnDeleteAllQueue.disabled = false;
                        btnDeleteAllQueue.innerHTML = '<i class="fas fa-trash me-1"></i> Delete All';
                    }
                })
                .catch(err => {
                    alert('Error executing action: ' + err.message);
                    btnDeleteAllQueue.disabled = false;
                    btnDeleteAllQueue.innerHTML = '<i class="fas fa-trash me-1"></i> Delete All';
                });
            }
        });
    }

    // Toggle Stop/Start Posting Button
    const btnTogglePosting = document.getElementById('btnTogglePosting');
    if (btnTogglePosting) {
        btnTogglePosting.addEventListener('click', function(e) {
            e.preventDefault();
            const currentState = btnTogglePosting.getAttribute('data-posting-state');
            const isStop = (currentState === 'active');
            const action = isStop ? 'stop_posting' : 'start_posting';

            const confirmMsg = isStop
                ? '⛔ Are you sure you want to STOP all posting?\n\n• All scheduled posts will be cancelled\n• All active schedules will be paused'
                : '▶️ Are you sure you want to START posting?\n\n• All paused schedules will be activated\n• Stopped posts will be re-scheduled';

            if (!confirm(confirmMsg)) return;

            btnTogglePosting.disabled = true;
            btnTogglePosting.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> ' + (isStop ? 'Stopping...' : 'Starting...');

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('action', action);

            fetch('ajax/ajax_queue_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || (isStop ? 'Posting stopped!' : 'Posting started!'));
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    btnTogglePosting.disabled = false;
                    btnTogglePosting.innerHTML = isStop
                        ? '<i class="fas fa-stop-circle me-1"></i> Stop Posting'
                        : '<i class="fas fa-play-circle me-1"></i> Start Posting';
                }
            })
            .catch(err => {
                alert('Request error: ' + err.message);
                btnTogglePosting.disabled = false;
                btnTogglePosting.innerHTML = isStop
                    ? '<i class="fas fa-stop-circle me-1"></i> Stop Posting'
                    : '<i class="fas fa-play-circle me-1"></i> Start Posting';
            });
        });
    }

    // Track user active typing time
    let lastUserTypingTime = 0;
    let isRefreshing = false;
    let autoRefreshCountdown = 30;

    // Helper to check if user is selecting checkboxes or viewing a modal
    function isUserBusyWithQueue() {
        const checkedCount = document.querySelectorAll('.queue-chk:checked, #checkAllQueue:checked').length;
        if (checkedCount > 0) return true;
        if (document.querySelector('.modal.show')) return true;
        return false;
    }

    // Global safe refresh function (Manual click forces refresh; auto timer skips if user is busy)
    window.safelyRefreshQueuePage = function(forceManual = false) {
        if (isRefreshing) return;

        if (!forceManual && isUserBusyWithQueue()) {
            console.log('[Queue] Skipping auto-refresh: User has selected items or open modal.');
            return;
        }

        isRefreshing = true;
        const icon = document.getElementById('queueRefreshIcon');
        const timerElem = document.getElementById('queueAutoRefreshTimer');
        if (icon) icon.classList.add('fa-spin');
        if (timerElem) timerElem.textContent = '...';

        // Trigger queue processor in background asynchronously
        try {
            fetch('ajax/ajax_process_queue.php', { method: 'POST' }).catch(() => {});
        } catch(e) {}

        // Reload page
        setTimeout(() => {
            window.location.reload();
        }, 500);
    };

    // Manual Process Queue Button
    const btnManualProcessQueue = document.getElementById('btnManualProcessQueue');
    if (btnManualProcessQueue) {
        btnManualProcessQueue.addEventListener('click', function() {
            if (!confirm('Do you want to process and publish due posts right now?')) return;
            btnManualProcessQueue.disabled = true;
            btnManualProcessQueue.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
            
            fetch('ajax/ajax_process_queue.php', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`Queue Processed!\nPublished: ${data.succeeded || 0}\nFailed: ${data.failed || 0}`);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to process queue.'));
                        btnManualProcessQueue.disabled = false;
                        btnManualProcessQueue.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Process Due Posts Now';
                    }
                })
                .catch(err => {
                    alert('Request error: ' + err.message);
                    btnManualProcessQueue.disabled = false;
                    btnManualProcessQueue.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Process Due Posts Now';
                });
        });
    }

    // Reset Stuck Publishing Button
    const btnResetStuck = document.getElementById('btnResetStuck');
    if (btnResetStuck) {
        btnResetStuck.addEventListener('click', function() {
            if (confirm('Reset all stuck publishing posts back to scheduled status?')) {
                const formData = new FormData();
                formData.append('_csrf_token', csrfToken);
                formData.append('action', 'reset_stuck_publishing');
                fetch('ajax/ajax_queue_actions.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message || 'Stuck posts reset successfully!');
                            window.location.reload();
                        } else {
                            alert('Reset failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(err => alert('Error: ' + err.message));
            }
        });
    }

    // Live 30-second countdown timer for auto-refresh
    setInterval(function() {
        if (isRefreshing) return;

        const timerElem = document.getElementById('queueAutoRefreshTimer');

        if (isUserBusyWithQueue()) {
            autoRefreshCountdown = 30;
            if (timerElem) timerElem.textContent = 'Paused';
            return;
        }

        autoRefreshCountdown--;
        if (autoRefreshCountdown <= 0) {
            autoRefreshCountdown = 30;
            if (timerElem) timerElem.textContent = '...';
            safelyRefreshQueuePage(false);
        } else {
            if (timerElem) timerElem.textContent = autoRefreshCountdown + 's';
        }
    }, 1000);
});
</script>
</div>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>