<?php
/**
 * WAPI SaaS - User Dashboard Sidebar Component
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$db = Database::getInstance();

// Get user credit balance
$isAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
$credits = $db->fetch("SELECT total_credits, used_credits FROM credits WHERE user_id = ?", [$_SESSION['user_id']]);
$creditBalance = $credits ? ($credits['total_credits'] - $credits['used_credits']) : 0;
$unreadNotifications = $db->count('notifications', "user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-user-block d-flex align-items-center gap-2">
            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
                <div class="fw-bold" style="font-size: 0.8125rem; line-height: 1.2;"><?= e($_SESSION['user_name'] ?? 'User'); ?></div>
                <div style="font-size: 0.6875rem; color: var(--text-muted);">
                    Credits: <span class="fw-bold text-primary"><?= $isAdmin ? 'Unlimited' : number_format($creditBalance); ?></span>
                </div>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <!-- Overview -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Overview</div>
            <a href="<?= baseUrl('dashboard/'); ?>" class="sidebar-link <?= $currentPage === 'index' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= baseUrl('dashboard/whatsapp.php'); ?>" class="sidebar-link <?= $currentPage === 'whatsapp' ? 'active' : ''; ?>">
                <i class="bi bi-whatsapp"></i>
                <span>WhatsApp Setup</span>
            </a>
        </div>

        <!-- Messaging -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Messaging</div>
            <a href="<?= baseUrl('dashboard/messages.php'); ?>" class="sidebar-link <?= $currentPage === 'messages' ? 'active' : ''; ?>">
                <i class="bi bi-send-fill"></i>
                <span>Send Message</span>
            </a>
            <a href="<?= baseUrl('dashboard/bulk-messages.php'); ?>" class="sidebar-link <?= $currentPage === 'bulk-messages' ? 'active' : ''; ?>">
                <i class="bi bi-megaphone-fill"></i>
                <span>Bulk Messages</span>
            </a>
            <a href="<?= baseUrl('dashboard/templates.php'); ?>" class="sidebar-link <?= $currentPage === 'templates' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Templates</span>
            </a>

            <a href="<?= baseUrl('dashboard/live-chat.php'); ?>" class="sidebar-link <?= $currentPage === 'live-chat' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots-fill"></i>
                <span>Live Chat</span>
            </a>

        </div>

        <!-- Automations -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Automations</div>
            <a href="<?= baseUrl('dashboard/chatbot-builder.php'); ?>" class="sidebar-link <?= $currentPage === 'chatbot-builder' ? 'active' : ''; ?>">
                <i class="bi bi-robot"></i>
                <span>Chatbot Builder</span>
                <span class="badge rounded-pill bg-primary ms-auto" style="font-size: 0.6rem;">NEW</span>
            </a>
            <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>" class="sidebar-link <?= $currentPage === 'ai-chatbot' || $currentPage === 'ai-chatbot-editor' ? 'active' : ''; ?>">
                <i class="bi bi-stars"></i>
                <span>AI ChatBot Builder</span>
                <span class="badge rounded-pill ms-auto" style="font-size: 0.6rem; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff;">AI</span>
            </a>
        </div>

        <!-- CRM & Contacts -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">CRM & Contacts</div>
            <a href="<?= baseUrl('dashboard/crm.php'); ?>" class="sidebar-link <?= $currentPage === 'crm' ? 'active' : ''; ?>">
                <i class="bi bi-kanban-fill"></i>
                <span>WhatsApp CRM</span>
                <span class="badge rounded-pill bg-success ms-auto" style="font-size: 0.6rem;">PRO</span>
            </a>
            <a href="<?= baseUrl('dashboard/contacts.php'); ?>" class="sidebar-link <?= $currentPage === 'contacts' ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Contacts</span>
            </a>
        </div>

        <!-- Analytics -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Analytics</div>
            <a href="<?= baseUrl('dashboard/message-logs.php'); ?>" class="sidebar-link <?= $currentPage === 'message-logs' ? 'active' : ''; ?>">
                <i class="bi bi-list-check"></i>
                <span>Message Logs</span>
            </a>
            <a href="<?= baseUrl('dashboard/ai-analytics.php'); ?>" class="sidebar-link <?= $currentPage === 'ai-analytics' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>AI Analytics</span>
            </a>
            <a href="<?= baseUrl('dashboard/ai-conversations.php'); ?>" class="sidebar-link <?= $currentPage === 'ai-conversations' ? 'active' : ''; ?>">
                <i class="bi bi-chat-left-text"></i>
                <span>AI Conversations</span>
            </a>
        </div>

        <!-- Account -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="<?= baseUrl('dashboard/api-keys.php'); ?>" class="sidebar-link <?= $currentPage === 'api-keys' ? 'active' : ''; ?>">
                <i class="bi bi-key-fill"></i>
                <span>API Keys</span>
            </a>
            <a href="<?= baseUrl('dashboard/subscription.php'); ?>" class="sidebar-link <?= $currentPage === 'subscription' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card-fill"></i>
                <span>Subscription</span>
            </a>
            <a href="<?= baseUrl('dashboard/settings.php'); ?>" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
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
