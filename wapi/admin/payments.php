<?php
/**
 * WAPI SaaS - Admin Payments Management
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

// --- START: Auto-Migration for Live Database ---
try {
    $planIdExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'plan_id'");
    if (!$planIdExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `plan_id` INT(11) NULL AFTER `user_id`");
    }
    
    $utrExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'utr_number'");
    if (!$utrExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `utr_number` VARCHAR(100) NULL AFTER `razorpay_signature`");
    }

    $cycleExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'billing_cycle'");
    if (!$cycleExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `billing_cycle` VARCHAR(20) DEFAULT 'monthly' AFTER `plan_id`");
    }
} catch (Exception $e) {
    // Ignore error if it fails
}
// --- END: Auto-Migration ---

$hideNav = true; // Prevents landing page nav from appearing in admin

$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = '1';
$params = [];

if ($search) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR p.razorpay_payment_id LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($statusFilter) {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}

// Handle manual approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $paymentId = sanitizeInt($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($paymentId && $action === 'approve') {
        $payment = $db->fetch("SELECT * FROM payments WHERE id = ? AND status = 'pending'", [$paymentId]);
        if ($payment) {
            $db->beginTransaction();
            try {
                // Activate subscription
                $db->update('subscriptions', ['status' => 'cancelled'], "user_id = ? AND status = 'active'", [$payment['user_id']]);
                
                $startsAt = date('Y-m-d H:i:s');
                $billingCycle = $payment['billing_cycle'] ?? 'monthly';
                if ($billingCycle !== 'yearly') $billingCycle = 'monthly';
                $expiresAt = ($billingCycle === 'yearly') ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));
                
                $subscriptionId = $db->insert('subscriptions', [
                    'user_id' => $payment['user_id'],
                    'plan_id' => $payment['plan_id'],
                    'billing_cycle' => $billingCycle,
                    'amount' => $payment['amount'],
                    'status' => 'active',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt
                ]);

                // Update payment
                $db->update('payments', [
                    'subscription_id' => $subscriptionId,
                    'status' => 'success'
                ], 'id = ?', [$paymentId]);

                // Update credits
                $planDetails = $db->fetch("SELECT message_limit FROM plans WHERE id = ?", [$payment['plan_id']]);
                if ($planDetails) {
                    $db->update('credits', [
                        'total_credits' => $planDetails['message_limit'],
                        'used_credits' => 0
                    ], "user_id = ?", [$payment['user_id']]);
                }

                $db->commit();
                setFlash('success', 'Payment approved and subscription activated!');
            } catch (Exception $e) {
                $db->rollback();
                setFlash('danger', 'Error approving payment: ' . $e->getMessage());
            }
        }
    }
}

try {
    $totalPayments = $db->fetchColumn("SELECT COUNT(*) FROM payments p JOIN users u ON p.user_id = u.id WHERE {$where}", $params);
    $pagination = paginate($totalPayments, $page, 20);
    $payments = $db->fetchAll("SELECT p.*, u.name as user_name, u.email as user_email, s.billing_cycle, pl.name as plan_name, COALESCE(pl2.name, pl.name) as actual_plan_name FROM payments p JOIN users u ON p.user_id = u.id LEFT JOIN subscriptions s ON p.subscription_id = s.id LEFT JOIN plans pl ON s.plan_id = pl.id LEFT JOIN plans pl2 ON p.plan_id = pl2.id WHERE {$where} ORDER BY p.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

    $totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'") ?: 0;
    $monthlyRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?: 0;
} catch (Exception $e) {
    die("<h3>FATAL ERROR:</h3><p>" . $e->getMessage() . "</p><p>SQL: SELECT p.*, u.name as user_name, u.email as user_email, s.billing_cycle, pl.name as plan_name, COALESCE(pl2.name, pl.name) as actual_plan_name FROM payments p JOIN users u ON p.user_id = u.id LEFT JOIN subscriptions s ON p.subscription_id = s.id LEFT JOIN plans pl ON s.plan_id = pl.id LEFT JOIN plans pl2 ON p.plan_id = pl2.id</p>");
}

$pageTitle = 'Payments';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Payments</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Payments</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <!-- Revenue Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="bi bi-currency-rupee"></i></div>
                    <div>
                        <div class="stat-value"><?= formatCurrency($totalRevenue); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <div class="stat-value"><?= formatCurrency($monthlyRevenue); ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="bi bi-receipt"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalPayments; ?></div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">All Payments</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <div class="search-box"><i class="bi bi-search"></i><input name="search" class="form-control" placeholder="Search..." value="<?= e($search); ?>"></div>
                    <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="success" <?= $statusFilter === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Method</th><th>UTR / Payment ID</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments found</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?= strtoupper(substr($p['user_name'], 0, 1)); ?></div>
                                    <div>
                                        <div class="fw-bold"><?= e($p['user_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($p['user_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= e($p['actual_plan_name'] ?? $p['plan_name'] ?? '-'); ?></span></td>
                            <td class="fw-bold"><?= formatCurrency($p['amount']); ?></td>
                            <td><?= ucfirst($p['payment_method'] ?? 'Razorpay'); ?></td>
                            <td style="font-size: 0.8125rem;">
                                <code><?= e($p['utr_number'] ?? $p['razorpay_payment_id'] ?? '-'); ?></code>
                            </td>
                            <td><span class="status-badge status-<?= $p['status'] === 'success' ? 'active' : $p['status']; ?>"><?= ucfirst($p['status']); ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= formatDate($p['created_at']); ?></td>
                            <td>
                                <?php if ($p['status'] === 'pending' && $p['payment_method'] === 'UPI'): ?>
                                    <form method="POST" onsubmit="return confirm('Confirm this payment and activate user plan?')">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="payment_id" value="<?= $p['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Approve</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
