<?php
/**
 * WAPI SaaS - Admin Sidebar Component
 * Collapsible sidebar for admin panel
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Count unread notifications
$unreadCount = $db->count('notifications', "user_id = ? AND is_read = 0", [$_SESSION['user_id'] ?? 0]);
$totalUsers = $db->count('users', 'role = ?', ['user']);
?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <span class="fw-bold" style="font-size: 0.875rem; color: var(--text-muted);">
            <i class="bi bi-shield-check text-primary"></i> Admin Panel
        </span>
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <!-- Main -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>
            <a href="<?= baseUrl('admin/'); ?>" class="sidebar-link <?= $currentPage === 'index' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- Management -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Management</div>
            <a href="<?= baseUrl('admin/users.php'); ?>" class="sidebar-link <?= $currentPage === 'users' ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Users</span>
                <span class="badge"><?= $totalUsers; ?></span>
            </a>
            <a href="<?= baseUrl('admin/plans.php'); ?>" class="sidebar-link <?= $currentPage === 'plans' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card-2-front-fill"></i>
                <span>Plans</span>
            </a>
            <a href="<?= baseUrl('admin/payments.php'); ?>" class="sidebar-link <?= $currentPage === 'payments' ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Payments</span>
            </a>
            <a href="<?= baseUrl('admin/messages.php'); ?>" class="sidebar-link <?= $currentPage === 'messages' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots-fill"></i>
                <span>Messages</span>
            </a>
            <?php
                try {
                    $contactUnread = $db->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'") ?: 0;
                } catch (Exception $e) {
                    $contactUnread = 0;
                }
            ?>
            <a href="<?= baseUrl('admin/contact-messages.php'); ?>" class="sidebar-link <?= $currentPage === 'contact-messages' ? 'active' : ''; ?>">
                <i class="bi bi-envelope-paper-fill"></i>
                <span>Contact Messages</span>
                <?php if ($contactUnread > 0): ?>
                    <span class="badge"><?= $contactUnread; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= baseUrl('admin/ai-bots.php'); ?>" class="sidebar-link <?= $currentPage === 'ai-bots' ? 'active' : ''; ?>">
                <i class="bi bi-robot"></i>
                <span>AI Bots</span>
                <span class="badge rounded-pill ms-auto" style="font-size: 0.6rem; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff;">AI</span>
            </a>
            <a href="<?= baseUrl('admin/ai-settings.php'); ?>" class="sidebar-link <?= $currentPage === 'ai-settings' ? 'active' : ''; ?>">
                <i class="bi bi-cpu"></i>
                <span>AI Settings</span>
            </a>
        </div>

        <!-- Content -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Content</div>
            <a href="<?= baseUrl('admin/content.php'); ?>" class="sidebar-link <?= $currentPage === 'content' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>CMS</span>
            </a>
            <a href="<?= baseUrl('admin/templates.php'); ?>" class="sidebar-link <?= $currentPage === 'templates' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-code-fill"></i>
                <span>Templates</span>
            </a>
        </div>

        <!-- Settings -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Settings</div>
            <a href="<?= baseUrl('admin/settings.php'); ?>" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear-fill"></i>
                <span>General</span>
            </a>
            <a href="<?= baseUrl('admin/api-settings.php'); ?>" class="sidebar-link <?= $currentPage === 'api-settings' ? 'active' : ''; ?>">
                <i class="bi bi-whatsapp"></i>
                <span>WhatsApp API</span>
            </a>
            <a href="<?= baseUrl('admin/email-settings.php'); ?>" class="sidebar-link <?= $currentPage === 'email-settings' ? 'active' : ''; ?>">
                <i class="bi bi-envelope-fill"></i>
                <span>Email / SMTP</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= baseUrl(); ?>" class="sidebar-link">
            <i class="bi bi-globe"></i>
            <span>View Website</span>
        </a>
        <a href="<?= baseUrl('auth/logout.php'); ?>" class="sidebar-link" style="color: var(--danger);">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
