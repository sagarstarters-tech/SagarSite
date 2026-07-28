<?php
/**
 * WAPI SaaS - Admin AI Bots Management
 * Monitor, suspend, and manage all AI bots across all users
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();
$hideNav = true;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';
    $botId = sanitizeInt($_POST['bot_id'] ?? 0);

    if ($action === 'suspend' && $botId > 0) {
        $db->update('ai_bots', ['status' => 'suspended'], 'id = ?', [$botId]);
        setFlash('success', 'Bot suspended successfully.');
    } elseif ($action === 'activate' && $botId > 0) {
        $db->update('ai_bots', ['status' => 'active'], 'id = ?', [$botId]);
        setFlash('success', 'Bot activated successfully.');
    } elseif ($action === 'delete' && $botId > 0) {
        $db->delete('ai_bots', 'id = ?', [$botId]);
        setFlash('success', 'Bot deleted permanently.');
    }
    redirect('admin/ai-bots.php');
}

// Stats
try { $totalBots = $db->count('ai_bots', '1'); } catch (Exception $e) { $totalBots = 0; }
try { $activeBots = $db->count('ai_bots', "status = 'active'"); } catch (Exception $e) { $activeBots = 0; }
try { $totalConversations = $db->count('ai_conversations', '1'); } catch (Exception $e) { $totalConversations = 0; }
try { $totalTokens = $db->fetchColumn("SELECT COALESCE(SUM(tokens_used), 0) FROM ai_messages WHERE sender_type = 'ai'") ?: 0; } catch (Exception $e) { $totalTokens = 0; }

// Search & filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];

if ($search) {
    $where .= " AND (b.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter && in_array($statusFilter, ['active', 'inactive', 'suspended'])) {
    $where .= " AND b.status = ?";
    $params[] = $statusFilter;
}

try {
    $totalFiltered = $db->fetchColumn("SELECT COUNT(*) FROM ai_bots b JOIN users u ON b.user_id = u.id WHERE $where", $params) ?: 0;
    $totalPages = max(1, ceil($totalFiltered / $perPage));
    // Fetch bots with owner info
    $bots = $db->fetchAll("SELECT b.*, u.name as owner_name, u.email as owner_email,
        (SELECT COUNT(*) FROM ai_conversations WHERE bot_id = b.id) as conv_count,
        (SELECT COALESCE(SUM(tokens_used), 0) FROM ai_messages WHERE bot_id = b.id AND sender_type = 'ai') as tokens_used
        FROM ai_bots b 
        JOIN users u ON b.user_id = u.id 
        WHERE $where 
        ORDER BY b.created_at DESC 
        LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
    $totalFiltered = 0;
    $totalPages = 1;
    $bots = [];
}

$pageTitle = 'AI Bots Management';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">🤖 AI Bots Management</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('admin/'); ?>">Admin</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>AI Bots</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('admin/ai-settings.php'); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear me-1"></i>AI Settings</a>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="bi bi-robot"></i></div>
                    <div><div class="stat-value"><?= $totalBots; ?></div><div class="stat-label">Total Bots</div></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                    <div><div class="stat-value"><?= $activeBots; ?></div><div class="stat-label">Active Bots</div></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon whatsapp"><i class="bi bi-chat-dots-fill"></i></div>
                    <div><div class="stat-value"><?= formatNumber($totalConversations); ?></div><div class="stat-label">Total Conversations</div></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon info"><i class="bi bi-cpu-fill"></i></div>
                    <div><div class="stat-value"><?= formatNumber($totalTokens); ?></div><div class="stat-label">Tokens Consumed</div></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3" style="border-radius: var(--border-radius);">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by bot name, owner..." value="<?= e($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bots Table -->
        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title">All AI Bots (<?= $totalFiltered; ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bot Name</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Model</th>
                            <th>Conversations</th>
                            <th>Tokens</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bots)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox" style="font-size: 1.5rem;"></i><br>No AI bots found</td></tr>
                        <?php else: ?>
                        <?php foreach ($bots as $bot): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($bot['name']); ?></div>
                                <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= e(substr($bot['uuid'], 0, 8)); ?>...</div>
                            </td>
                            <td>
                                <div style="font-size: 0.8125rem;"><?= e($bot['owner_name']); ?></div>
                                <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= e($bot['owner_email']); ?></div>
                            </td>
                            <td>
                                <?php
                                $sc = match($bot['status']) { 'active' => 'success', 'suspended' => 'danger', default => 'secondary' };
                                ?>
                                <span class="badge bg-<?= $sc; ?>" style="font-size: 0.6875rem;"><?= ucfirst($bot['status']); ?></span>
                            </td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary); font-size: 0.6875rem;"><?= strtoupper(e($bot['ai_model'])); ?></span></td>
                            <td><?= formatNumber($bot['conv_count']); ?></td>
                            <td><?= formatNumber($bot['tokens_used']); ?></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= formatDate($bot['created_at']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($bot['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this bot?')">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <input type="hidden" name="bot_id" value="<?= $bot['id']; ?>">
                                        <button class="btn btn-sm btn-outline-warning" title="Suspend"><i class="bi bi-pause-circle"></i></button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="bot_id" value="<?= $bot['id']; ?>">
                                        <button class="btn btn-sm btn-outline-success" title="Activate"><i class="bi bi-play-circle"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this bot and all its data?')">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="bot_id" value="<?= $bot['id']; ?>">
                                        <button class="btn btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);" title="Delete"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
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
                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                    Page <?= $page; ?> of <?= $totalPages; ?>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">«</a></li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : ''; ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?= $p; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">»</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
