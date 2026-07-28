<?php
/**
 * WAPI SaaS - AI Conversation History
 * Searchable, filterable view of all AI conversations
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];
$hideNav = true;

// Filters
$botFilter = sanitizeInt($_GET['bot'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch bots for filter dropdown
try {
    $bots = $db->fetchAll("SELECT id, name FROM ai_bots WHERE user_id = ? ORDER BY name", [$userId]);
} catch (Exception $e) { $bots = []; }

// Build query
$where = "c.user_id = ?";
$params = [$userId];

if ($botFilter > 0) {
    $where .= " AND c.bot_id = ?";
    $params[] = $botFilter;
}
if ($statusFilter && in_array($statusFilter, ['active', 'resolved', 'handed_over', 'expired'])) {
    $where .= " AND c.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where .= " AND (c.customer_phone LIKE ? OR c.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total
try {
    $totalConversations = $db->fetchColumn("SELECT COUNT(*) FROM ai_conversations c WHERE $where", $params) ?: 0;
    $totalPages = max(1, ceil($totalConversations / $perPage));
    // Fetch conversations
    $conversations = $db->fetchAll("SELECT c.*, b.name as bot_name 
        FROM ai_conversations c 
        JOIN ai_bots b ON c.bot_id = b.id 
        WHERE $where 
        ORDER BY c.last_message_at DESC 
        LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
    $totalConversations = 0;
    $totalPages = 1;
    $conversations = [];
}

$pageTitle = 'AI Conversations';
$extraCss = [asset('assets/css/dashboard.css'), asset('assets/css/ai-chatbot.css')];
$extraJs = [asset('assets/js/ai-chatbot.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">💬 AI Conversations</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>AI Conversations</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
            </div>
        </div>

        <!-- Filters -->
        <div class="ai-editor-card" style="position: relative; overflow: hidden; margin-bottom: 1.5rem;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); opacity: 0.7;"></div>
            <h6 style="margin-bottom: 1.25rem;"><i class="bi bi-funnel"></i> Filter Conversations</h6>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">
                        <i class="bi bi-robot me-1" style="color: #667eea;"></i>Bot
                    </label>
                    <select name="bot" class="form-select form-select-sm" style="border-radius: 8px; border-color: var(--border-color);">
                        <option value="0">All Bots</option>
                        <?php foreach ($bots as $b): ?>
                        <option value="<?= $b['id']; ?>" <?= $botFilter == $b['id'] ? 'selected' : ''; ?>><?= e($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">
                        <i class="bi bi-circle-fill me-1" style="color: #10b981; font-size: 0.5rem; vertical-align: middle;"></i>Status
                    </label>
                    <select name="status" class="form-select form-select-sm" style="border-radius: 8px; border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="handed_over" <?= $statusFilter === 'handed_over' ? 'selected' : ''; ?>>Handed Over</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">
                        <i class="bi bi-search me-1" style="color: #667eea;"></i>Search
                    </label>
                    <input type="text" name="search" class="form-control form-control-sm" style="border-radius: 8px; border-color: var(--border-color);" placeholder="Search by phone or name..." value="<?= e($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-ai btn-sm w-100" style="border-radius: 8px; padding: 0.4rem 0.75rem;"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                </div>
            </form>
        </div>

        <!-- Conversations Table -->
        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title"><i class="bi bi-chat-dots me-2" style="color: #667eea;"></i>Conversations <span style="font-weight: 400; color: var(--text-muted); font-size: 0.875rem;">(<?= number_format($totalConversations); ?>)</span></h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Bot</th>
                            <th>Messages</th>
                            <th>Status</th>
                            <th>Resolved By</th>
                            <th>Last Activity</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conversations)): ?>
                        <tr><td colspan="7" class="p-0" style="border: none;">
                            <div class="ai-empty-state" style="border: none; border-radius: 0;">
                                <div class="empty-icon"><i class="bi bi-chat-square-dots"></i></div>
                                <h4>No Conversations Yet</h4>
                                <p>No conversations match your current filters. Try adjusting your search criteria or wait for new customer interactions.</p>
                                <a href="?" class="btn btn-ai-outline btn-sm" style="border-radius: 8px;"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear Filters</a>
                            </div>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                        <tr style="cursor: pointer;" onclick="toggleConversation(<?= $conv['id']; ?>)">
                            <td>
                                <div class="fw-semibold"><?= e($conv['customer_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($conv['customer_phone']); ?></div>
                            </td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary); font-size: 0.75rem;"><?= e($conv['bot_name']); ?></span></td>
                            <td>
                                <span style="font-size: 0.8125rem;"><?= $conv['messages_count']; ?></span>
                                <span style="font-size: 0.6875rem; color: var(--text-muted);">
                                    (AI: <?= $conv['ai_messages_count']; ?>)
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClasses = ['active' => 'status-active', 'resolved' => 'status-sent', 'handed_over' => 'status-pending', 'expired' => 'status-inactive'];
                                $statusClass = $statusClasses[$conv['status']] ?? 'status-inactive';
                                ?>
                                <span class="status-badge <?= $statusClass; ?>"><?= ucfirst(str_replace('_', ' ', $conv['status'])); ?></span>
                            </td>
                            <td>
                                <?php if ($conv['resolved_by']): ?>
                                <span style="font-size: 0.8125rem;"><i class="bi bi-<?= $conv['resolved_by'] === 'ai' ? 'robot' : 'person'; ?> me-1"></i><?= ucfirst($conv['resolved_by']); ?></span>
                                <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= $conv['last_message_at'] ? timeAgo($conv['last_message_at']) : '—'; ?></td>
                            <td><i class="bi bi-chevron-down" style="font-size: 0.75rem; color: var(--text-muted);"></i></td>
                        </tr>
                        <tr id="conv-detail-<?= $conv['id']; ?>" style="display: none;">
                            <td colspan="7" class="p-0">
                                <!-- Messages loaded via JS -->
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3 border-top">
                <div style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 500;">
                    Showing <strong><?= $offset + 1; ?>–<?= min($offset + $perPage, $totalConversations); ?></strong> of <strong><?= $totalConversations; ?></strong> conversations
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" style="gap: 4px;">
                        <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" style="border-radius: 8px; border-color: var(--border-color); color: #667eea; font-weight: 600;">«</a></li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])); ?>" style="border-radius: 8px; <?= $p == $page ? 'background: linear-gradient(135deg, #667eea, #764ba2); border-color: transparent; color: #fff; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);' : 'border-color: var(--border-color); color: var(--text-secondary);'; ?> font-weight: 600;"><?= $p; ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" style="border-radius: 8px; border-color: var(--border-color); color: #667eea; font-weight: 600;">»</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
window.APP_BASE = '<?= baseUrl(); ?>';
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
