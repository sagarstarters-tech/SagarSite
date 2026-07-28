<?php
/**
 * WAPI SaaS - User Dashboard
 * Main dashboard with usage stats and quick actions
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];
$hideNav = true; // Prevents landing page nav from appearing in dashboard

// Get user stats
$credits = $db->fetch("SELECT * FROM credits WHERE user_id = ?", [$userId]);
$creditBalance = $credits ? ($credits['total_credits'] - $credits['used_credits']) : 0;
$creditTotal = $credits['total_credits'] ?? 0;

// Message stats
$totalMessages = $db->count('messages', 'user_id = ?', [$userId]);
$sentMessages = $db->count('messages', "user_id = ? AND status = 'sent'", [$userId]);
$deliveredMessages = $db->count('messages', "user_id = ? AND status = 'delivered'", [$userId]);
$failedMessages = $db->count('messages', "user_id = ? AND status = 'failed'", [$userId]);
$todayMessages = $db->count('messages', 'user_id = ? AND DATE(created_at) = CURDATE()', [$userId]);

// Contacts count
$totalContacts = $db->count('contacts', 'user_id = ?', [$userId]);

// Active subscription
$subscription = $db->fetch("SELECT s.*, p.name as plan_name, p.message_limit, p.contacts_limit FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.created_at DESC LIMIT 1", [$userId]);

// Recent messages
$recentMessages = $db->fetchAll("SELECT m.*, c.name as contact_name FROM messages m LEFT JOIN contacts c ON m.contact_id = c.id WHERE m.user_id = ? ORDER BY m.created_at DESC LIMIT 5", [$userId]);

// API Keys count
$apiKeysCount = $db->count('api_keys', "user_id = ? AND is_active = 1", [$userId]);

// Message chart data (last 7 days grouped by status)
$chartData = $db->fetchAll("SELECT DATE(created_at) as date, status, COUNT(*) as count FROM messages WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at), status ORDER BY date ASC", [$userId]);

// Fetch User & WA Data for Alerts
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? LIMIT 1", [$userId]);
$isEmailVerified = !empty($user['email_verified']);
$isWaVerified = !empty($waAccount['phone_number_id']);

$pageTitle = 'Dashboard';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Welcome, <?= e($_SESSION['user_name']); ?>! 👋</h1>
                <div class="dash-breadcrumb">
                    <span>Here's your overview for today</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('dashboard/messages.php'); ?>" class="btn btn-primary btn-sm"><i class="bi bi-send-fill"></i> Send Message</a>
            </div>
        </div>

        <!-- Top Credits Bar -->
        <div class="top-credits-bar">
            <div class="credit-item">
                <div class="credit-circle"><?= $creditTotal > 0 ? round(($sentMessages/$creditTotal)*100) : 0; ?>%</div>
                <div class="credit-text">
                    <span class="credit-title">Message Credits</span>
                    <span class="credit-value"><?= formatNumber($sentMessages); ?>/<?= formatNumber($creditTotal); ?> Credits</span>
                </div>
            </div>
            <div class="credit-item">
                <div class="credit-circle"><?= $subscription && $subscription['contacts_limit'] > 0 ? round(($totalContacts/$subscription['contacts_limit'])*100) : 0; ?>%</div>
                <div class="credit-text">
                    <span class="credit-title">Contact Credits</span>
                    <span class="credit-value"><?= formatNumber($totalContacts); ?>/<?= $subscription ? formatNumber($subscription['contacts_limit']) : '0'; ?> Credits</span>
                </div>
            </div>
            <div class="credit-item">
                <div class="credit-circle">0%</div>
                <div class="credit-text">
                    <span class="credit-title">AI Token Credits</span>
                    <span class="credit-value">0/∞ Credits</span>
                </div>
            </div>
            <div class="ms-auto">
                <a href="<?= baseUrl('dashboard/subscription.php'); ?>" class="btn btn-sm btn-light text-success fw-bold px-3 border-0" style="border-radius: 20px;"><i class="bi bi-arrow-up-circle-fill"></i> Upgrade Server</a>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!$isEmailVerified): ?>
        <div class="dash-alert-box alert-yellow" id="alertEmail">
            <div class="dash-alert-icon"><i class="bi bi-envelope-open"></i></div>
            <div class="dash-alert-content">
                <h6>Verify Email : Email is not verified yet. Please verify your email.</h6>
                <p>Click the link to get started : <a href="<?= baseUrl('auth/verify-email.php'); ?>">Start Email Verification</a></p>
            </div>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>

        <?php if (!$isWaVerified): ?>
        <div class="dash-alert-box alert-yellow" id="alertWhatsapp">
            <div class="dash-alert-icon"><i class="bi bi-whatsapp"></i></div>
            <div class="dash-alert-content">
                <h6>Verify WhatsApp Number : WhatsApp phone number is not verified yet. Please verify your WhatsApp phone number.</h6>
                <p>Click the link to get started : <a href="<?= baseUrl('dashboard/whatsapp.php'); ?>">Start WhatsApp Verification</a></p>
            </div>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>

        <div class="dash-alert-box alert-gray" id="alertChannel">
            <div class="dash-alert-icon"><i class="bi bi-bell"></i></div>
            <div class="dash-alert-content">
                <h6><i class="bi bi-megaphone"></i> Join the <?= e($settings->get('site_name', 'WAPI')); ?> WhatsApp Channel</h6>
                <p>Get instant updates, product news, and important announcements — all in one place. 🚀 <a href="<?= e($settings->get('whatsapp_channel_url', '#')); ?>" target="_blank">👉 Join our WhatsApp Channel</a></p>
            </div>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>

        <div class="dash-alert-box alert-blue" id="alertPricing">
            <div class="dash-alert-icon"><i class="bi bi-bell-fill"></i></div>
            <div class="dash-alert-content">
                <h6><i class="bi bi-megaphone text-danger"></i> Important Pricing Update</h6>
                <p><strong><?= e($settings->get('site_name', 'WAPI')); ?> pricing will increase soon. ⏳</strong><br>
                🔒 Upgrade to a <strong>yearly plan</strong> now to <strong>lock your current price for a lifetime</strong>.<br>
                ✅ Enable <strong>auto-payment</strong> to continue at the <strong>same price forever</strong>, even after future increases.</p>
                <p class="mt-2 mb-0"><a href="<?= baseUrl('dashboard/subscription.php'); ?>">👉 View Pricing & Upgrade Now</a></p>
            </div>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-4">
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
                    <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= formatNumber($totalContacts); ?></div>
                        <div class="stat-label">Total Contacts</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="bi bi-coin"></i></div>
                    <div>
                        <div class="stat-value"><?= formatNumber($creditBalance); ?></div>
                        <div class="stat-label">Credits Remaining</div>
                        <?php if ($creditTotal > 0): ?>
                        <div class="stat-change">
                            <div style="background: var(--border-color); border-radius: 4px; height: 4px; width: 80px; overflow: hidden;">
                                <div style="background: var(--success); height: 100%; width: <?= min(100, ($creditBalance / $creditTotal) * 100); ?>%;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon info"><i class="bi bi-key-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $apiKeysCount; ?></div>
                        <div class="stat-label">Active API Keys</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Message Chart -->
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">Messages (Last 7 Days)</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="messagesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Current Plan -->
            <div class="col-lg-4">
                <div class="card h-100" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-credit-card-2-front text-primary"></i> Current Plan</h5>
                        <?php if ($subscription): ?>
                        <div class="mb-3">
                            <div class="badge-custom" style="background: var(--primary-bg); color: var(--primary); font-size: 1rem; padding: 6px 16px;">
                                <?= e($subscription['plan_name']); ?>
                            </div>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <div class="mb-2"><i class="bi bi-calendar3 me-2"></i> Expires: <?= formatDate($subscription['expires_at']); ?></div>
                            <div class="mb-2"><i class="bi bi-chat-dots me-2"></i> Messages: <?= number_format($subscription['message_limit']); ?>/mo</div>
                            <div class="mb-2"><i class="bi bi-people me-2"></i> Contacts: <?= number_format($subscription['contacts_limit']); ?></div>
                        </div>
                        <a href="<?= baseUrl('dashboard/subscription.php'); ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                            <i class="bi bi-arrow-up-circle"></i> Upgrade Plan
                        </a>
                        <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-credit-card-2-front" style="font-size: 2rem; color: var(--text-muted);"></i>
                            <p class="text-muted mt-2 mb-3">No active plan</p>
                            <a href="<?= baseUrl('dashboard/subscription.php'); ?>" class="btn btn-primary btn-sm">Choose a Plan</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions + Recent Messages -->
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="<?= baseUrl('dashboard/messages.php'); ?>" class="btn btn-outline-primary text-start"><i class="bi bi-send-fill me-2"></i> Send Message</a>
                            <a href="<?= baseUrl('dashboard/bulk-messages.php'); ?>" class="btn btn-outline-primary text-start"><i class="bi bi-megaphone-fill me-2"></i> Bulk Message</a>
                            <a href="<?= baseUrl('dashboard/contacts.php'); ?>" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus-fill me-2"></i> Add Contact</a>
                            <a href="<?= baseUrl('dashboard/api-keys.php'); ?>" class="btn btn-outline-primary text-start"><i class="bi bi-key-fill me-2"></i> Generate API Key</a>
                            <a href="<?= baseUrl('dashboard/templates.php'); ?>" class="btn btn-outline-primary text-start"><i class="bi bi-file-earmark-text-fill me-2"></i> Create Template</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="data-table">
                    <div class="data-table-header">
                        <h5 class="data-table-title">Recent Messages</h5>
                        <a href="<?= baseUrl('dashboard/message-logs.php'); ?>" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>To</th><th>Type</th><th>Status</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php if (empty($recentMessages)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox" style="font-size: 1.5rem;"></i><br>No messages yet. <a href="<?= baseUrl('dashboard/messages.php'); ?>">Send your first message</a></td></tr>
                                <?php else: ?>
                                <?php foreach ($recentMessages as $msg): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($msg['contact_name'] ?? $msg['to_number']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($msg['to_number']); ?></div>
                                    </td>
                                    <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($msg['type']); ?></span></td>
                                    <td><span class="status-badge status-<?= $msg['status']; ?>"><?= ucfirst($msg['status']); ?></span></td>
                                    <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= timeAgo($msg['created_at']); ?></td>
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

<script>
window.chartData = { messages: <?= json_encode($chartData); ?>, totals: { sent: <?= $sentMessages; ?>, delivered: <?= $deliveredMessages; ?>, failed: <?= $failedMessages; ?>, queued: 0 } };

document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$subscription): ?>
        // Force Subscription Modal
        var subModal = new bootstrap.Modal(document.getElementById('onboardingSubModal'), {
            backdrop: 'static',
            keyboard: false
        });
        subModal.show();
    <?php elseif (!$isWaVerified): ?>
        // Force WhatsApp Setup Modal
        var waModal = new bootstrap.Modal(document.getElementById('onboardingWaModal'), {
            backdrop: 'static',
            keyboard: false
        });
        waModal.show();
    <?php endif; ?>
});
</script>

<!-- Subscription Onboarding Modal -->
<?php if (!$subscription): ?>
<div class="modal fade" id="onboardingSubModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-body p-5 text-center">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background: rgba(108, 99, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="bi bi-credit-card-2-front-fill" style="font-size: 2.5rem; color: var(--primary);"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3">Welcome to <?= e($settings->get('site_name', 'WAPI')); ?>! 👋</h3>
                <p class="text-muted mb-4" style="font-size: 1.1rem;">
                    To get started with sending WhatsApp messages, you need to select a subscription plan. We offer flexible plans tailored to your needs.
                </p>
                <div class="d-grid gap-3">
                    <a href="<?= baseUrl('dashboard/subscription.php'); ?>" class="btn btn-primary btn-lg fw-bold" style="padding: 12px;">
                        Choose a Plan to Continue <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- WhatsApp Setup Onboarding Modal -->
<?php if ($subscription && !$isWaVerified): ?>
<div class="modal fade" id="onboardingWaModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(21, 163, 98, 0.2);">
            <div class="modal-body p-5 text-center">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background: rgba(21, 163, 98, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="bi bi-whatsapp" style="font-size: 2.5rem; color: #15a362;"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3">Setup WhatsApp API 📱</h3>
                <p class="text-muted mb-4" style="font-size: 1.1rem;">
                    Great! You have an active subscription. The final step is to connect your Facebook WhatsApp Cloud API account.
                </p>
                <div class="alert alert-warning text-start mb-4" style="font-size: 0.9rem; border-left: 4px solid #ffc107;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> You must configure your WhatsApp credentials before you can send any messages.
                </div>
                <div class="d-grid gap-3">
                    <a href="<?= baseUrl('dashboard/whatsapp.php'); ?>" class="btn btn-success btn-lg fw-bold" style="padding: 12px; background-color: #15a362; border-color: #15a362;">
                        Configure WhatsApp Now <i class="bi bi-gear-fill ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
