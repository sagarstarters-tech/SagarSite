<?php
/**
 * WAPI SaaS - Admin Dashboard
 * Main admin panel with analytics overview
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

Auth::requireAdmin();

$hideNav = true; // Prevents landing page nav from appearing in admin

$db = Database::getInstance();
$settings = new Settings();

// Dashboard Stats
$totalUsers = $db->count('users', 'role = ?', ['user']);
$activeSubscriptions = $db->count('subscriptions', 'status = ?', ['active']);
$totalMessages = $db->count('messages', '1');
$totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'") ?: 0;

// Recent Users
$recentUsers = $db->fetchAll("SELECT id, name, email, status, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");

// Recent Payments
$recentPayments = $db->fetchAll("SELECT p.*, u.name as user_name, u.email as user_email FROM payments p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 5");

// Message stats (last 7 days)
$messageStats = $db->fetchAll("SELECT DATE(created_at) as date, COUNT(*) as count, status FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at), status ORDER BY date ASC");

// Today's stats
$todayMessages = $db->count('messages', 'DATE(created_at) = CURDATE()');
$todayUsers = $db->count('users', 'DATE(created_at) = CURDATE() AND role = ?', ['user']);

$pageTitle = 'Admin Dashboard';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Dashboard</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('admin/'); ?>">Admin</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Dashboard</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    <i class="bi bi-calendar3"></i> <?= date('d M Y'); ?>
                </span>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= formatNumber($totalUsers); ?></div>
                        <div class="stat-label">Total Users</div>
                        <?php if ($todayUsers > 0): ?>
                        <div class="stat-change up"><i class="bi bi-arrow-up"></i> +<?= $todayUsers; ?> today</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="bi bi-credit-card-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $activeSubscriptions; ?></div>
                        <div class="stat-label">Active Subscriptions</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon whatsapp"><i class="bi bi-chat-dots-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= formatNumber($totalMessages); ?></div>
                        <div class="stat-label">Total Messages</div>
                        <?php if ($todayMessages > 0): ?>
                        <div class="stat-change up"><i class="bi bi-arrow-up"></i> +<?= $todayMessages; ?> today</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="bi bi-currency-rupee"></i></div>
                    <div>
                        <div class="stat-value"><?= formatCurrency($totalRevenue); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">Message Analytics (Last 7 Days)</h5>
                        <select class="form-control" style="width: auto; padding: 4px 8px; font-size: 0.8125rem;">
                            <option>Last 7 Days</option>
                            <option>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="messagesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">Message Status</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="data-table">
                    <div class="data-table-header">
                        <h5 class="data-table-title">Recent Users</h5>
                        <a href="<?= baseUrl('admin/users.php'); ?>" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUsers)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No users yet</td></tr>
                                <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)); ?></div>
                                            <div>
                                                <div class="fw-bold" style="font-size: 0.9375rem;"><?= e($user['name']); ?></div>
                                                <div style="font-size: 0.8125rem; color: var(--text-muted);"><?= e($user['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge status-<?= $user['status']; ?>"><?= ucfirst($user['status']); ?></span></td>
                                    <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= timeAgo($user['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="data-table">
                    <div class="data-table-header">
                        <h5 class="data-table-title">Recent Payments</h5>
                        <a href="<?= baseUrl('admin/payments.php'); ?>" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No payments yet</td></tr>
                                <?php else: ?>
                                <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold" style="font-size: 0.875rem;"><?= e($payment['user_name']); ?></div>
                                    </td>
                                    <td class="fw-bold"><?= formatCurrency($payment['amount']); ?></td>
                                    <td><span class="status-badge status-<?= $payment['status'] === 'success' ? 'active' : $payment['status']; ?>"><?= ucfirst($payment['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Chart Data -->
<script>
    window.chartData = {
        messages: <?= json_encode($messageStats); ?>,
        totals: {
            sent: <?= $db->count('messages', "status = 'sent'"); ?>,
            delivered: <?= $db->count('messages', "status = 'delivered'"); ?>,
            failed: <?= $db->count('messages', "status = 'failed'"); ?>,
            queued: <?= $db->count('messages', "status = 'queued'"); ?>
        }
    };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
