<?php
/**
 * WAPI SaaS - AI ChatBot Builder
 * Lists all user's AI bots with management actions
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];
$hideNav = true;
$migrationNeeded = false;

// Fetch user's AI bots (wrapped in try-catch for pre-migration state)
try {
    $bots = $db->fetchAll("SELECT b.*, 
        (SELECT COUNT(*) FROM ai_conversations WHERE bot_id = b.id) as total_conversations,
        wa.phone_number as wa_phone, wa.business_name as wa_business
        FROM ai_bots b 
        LEFT JOIN whatsapp_accounts wa ON b.whatsapp_account_id = wa.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC", [$userId]);
} catch (Exception $e) {
    $bots = [];
    $migrationNeeded = true;
}

// Plan limit for AI bots
try {
    $subscription = $db->fetch("SELECT s.*, p.ai_bots_limit FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        WHERE s.user_id = ? AND s.status = 'active' 
        ORDER BY s.created_at DESC LIMIT 1", [$userId]);
    $botsLimit = $subscription['ai_bots_limit'] ?? 0;
} catch (Exception $e) {
    $botsLimit = 0;
}
$botsUsed = count($bots);

// Fetch WA accounts for display
$waAccounts = $db->fetchAll('SELECT id, phone_number, business_name FROM whatsapp_accounts WHERE user_id = ?', [$userId]);

$pageTitle = 'AI ChatBot Builder';
$extraCss = [asset('assets/css/dashboard.css'), asset('assets/css/ai-chatbot.css')];
$extraJs = ['https://cdn.jsdelivr.net/npm/sweetalert2@11', asset('assets/js/ai-chatbot.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">🤖 AI ChatBot Builder</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>AI ChatBot Builder</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('dashboard/ai-chatbot-editor.php'); ?>" class="btn btn-ai btn-sm" style="border-radius: 10px; padding: 0.5rem 1.25rem;">
                    <i class="bi bi-plus-lg me-1"></i> Create New Bot
                </a>
            </div>
        </div>

        <!-- Plan Usage Indicator -->
        <div class="plan-usage-bar">
            <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, rgba(102,126,234,0.12), rgba(118,75,162,0.12)); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="bi bi-robot" style="font-size: 1.25rem; color: #667eea;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="usage-label"><?= $botsUsed; ?> / <?= $botsLimit > 0 ? $botsLimit : '∞'; ?> AI Bots</span>
                    <?php if ($botsLimit > 0): ?>
                    <span class="usage-count"><?= round(($botsUsed / $botsLimit) * 100); ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="usage-progress">
                    <div class="usage-progress-fill" style="width: <?= $botsLimit > 0 ? min(100, ($botsUsed / $botsLimit) * 100) : 0; ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in" style="border-radius: 10px;"><i class="bi bi-check-circle-fill me-1"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if ($migrationNeeded): ?>
        <div class="alert alert-warning d-flex align-items-center gap-3" style="border-radius: 12px; border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.08);">
            <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.5rem; color: #f59e0b;"></i>
            <div>
                <strong>Database Setup Required</strong><br>
                <span style="font-size: 0.875rem; opacity: 0.85;">AI ChatBot Builder tables have not been created yet. Please run the SQL migration: <code>database/ai_chatbot_schema.sql</code></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($bots)): ?>
        <!-- Bot Cards Grid -->
        <div class="row g-4">
            <?php foreach ($bots as $bot): ?>
            <div class="col-lg-4 col-md-6" id="bot-card-<?= $bot['id']; ?>">
                <div class="ai-bot-card <?= $bot['status'] === 'active' ? 'bot-active' : ($bot['status'] === 'suspended' ? 'bot-suspended' : ''); ?>">
                    <div class="bot-header">
                        <div class="bot-icon">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-dot <?= $bot['status']; ?>"></span>
                            <span style="font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: <?php
                                if ($bot['status'] === 'active') echo '#10b981';
                                elseif ($bot['status'] === 'suspended') echo '#ef4444';
                                else echo '#9ca3af';
                            ?>;"><?= ucfirst(e($bot['status'])); ?></span>
                            <div class="dropdown">
                                <button class="btn btn-sm" data-bs-toggle="dropdown" style="border-radius: 8px; padding: 2px 6px; background: transparent; border: 1px solid var(--border-color);">
                                    <i class="bi bi-three-dots-vertical" style="font-size: 0.75rem;"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); border: 1px solid var(--border-color);">
                                    <li><a class="dropdown-item" href="<?= baseUrl('dashboard/ai-chatbot-editor.php?id=' . $bot['id']); ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="cloneBot(<?= $bot['id']; ?>, '<?= e(addslashes($bot['name'])); ?>')"><i class="bi bi-copy me-2"></i>Clone</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0)" onclick="toggleBotStatus(<?= $bot['id']; ?>, '<?= $bot['status']; ?>')">
                                            <i class="bi bi-<?= $bot['status'] === 'active' ? 'pause-circle' : 'play-circle'; ?> me-2"></i>
                                            <?= $bot['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                    </li>
                                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteBot(<?= $bot['id']; ?>, '<?= e(addslashes($bot['name'])); ?>')"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="bot-name"><?= e($bot['name']); ?></div>
                    <div class="bot-desc"><?= e($bot['description'] ?? 'No description provided'); ?></div>

                    <div class="bot-meta">
                        <?php if (!empty($bot['wa_phone'])): ?>
                        <span class="bot-meta-item" style="color: #15a362;">
                            <i class="bi bi-whatsapp"></i><?= e($bot['wa_phone']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($bot['ai_model'])): ?>
                        <?php
                            $modelClass = 'custom';
                            if (strpos($bot['ai_model'], 'gpt') !== false) $modelClass = 'gpt';
                            elseif ($bot['ai_model'] === 'gemini') $modelClass = 'gemini';
                            elseif ($bot['ai_model'] === 'claude') $modelClass = 'claude';
                        ?>
                        <span class="model-badge <?= $modelClass; ?>">
                            <i class="bi bi-cpu me-1"></i><?= e(strtoupper($bot['ai_model'])); ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($bot['language']) && $bot['language'] !== 'English'): ?>
                        <span class="bot-meta-item">
                            <i class="bi bi-translate"></i><?= e($bot['language']); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="bot-stats">
                        <div class="bot-stat">
                            <div class="bot-stat-value"><?= formatNumber($bot['total_conversations']); ?></div>
                            <div class="bot-stat-label">Conversations</div>
                        </div>
                        <div class="bot-stat">
                            <div class="bot-stat-value"><?= formatNumber($bot['total_messages_processed'] ?? 0); ?></div>
                            <div class="bot-stat-label">Messages</div>
                        </div>
                        <div class="bot-stat">
                            <div class="bot-stat-value"><?= formatNumber($bot['total_leads_captured'] ?? 0); ?></div>
                            <div class="bot-stat-label">Leads</div>
                        </div>
                    </div>

                    <div class="bot-actions">
                        <a href="<?= baseUrl('dashboard/ai-chatbot-editor.php?id=' . $bot['id']); ?>" class="btn btn-ai-outline btn-sm flex-grow-1" style="border-radius: 8px;">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </a>
                        <a href="<?= baseUrl('dashboard/ai-chatbot-editor.php?id=' . $bot['id']); ?>#pane-test" class="btn btn-ai btn-sm flex-grow-1" style="border-radius: 8px;">
                            <i class="bi bi-chat-dots me-1"></i>Test
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- Empty State -->
        <div class="ai-empty-state">
            <div class="empty-icon">
                <i class="bi bi-robot"></i>
            </div>
            <h4>No AI Bots Yet</h4>
            <p>Create your first AI-powered chatbot to automate WhatsApp conversations, handle customer queries, and generate leads 24/7.</p>
            <a href="<?= baseUrl('dashboard/ai-chatbot-editor.php'); ?>" class="btn btn-ai" style="border-radius: 10px; padding: 0.625rem 1.75rem;">
                <i class="bi bi-plus-lg me-1"></i> Create Your First AI Bot
            </a>

            <!-- Feature Highlights -->
            <div class="row g-3 mt-4" style="max-width: 700px; margin: 0 auto;">
                <div class="col-md-4">
                    <div style="padding: 1.25rem; border-radius: 12px; background: rgba(102,126,234,0.05); border: 1px solid rgba(102,126,234,0.1);">
                        <i class="bi bi-lightning-charge" style="font-size: 1.5rem; color: #667eea;"></i>
                        <div style="font-size: 0.8125rem; font-weight: 600; margin-top: 0.5rem; color: var(--text-primary);">Auto Replies</div>
                        <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 0.25rem;">AI handles customer queries instantly, 24/7</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="padding: 1.25rem; border-radius: 12px; background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.1);">
                        <i class="bi bi-people" style="font-size: 1.5rem; color: #10b981;"></i>
                        <div style="font-size: 0.8125rem; font-weight: 600; margin-top: 0.5rem; color: var(--text-primary);">Lead Capture</div>
                        <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 0.25rem;">Automatically collect customer info & leads</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="padding: 1.25rem; border-radius: 12px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.1);">
                        <i class="bi bi-person-check" style="font-size: 1.5rem; color: #f59e0b;"></i>
                        <div style="font-size: 0.8125rem; font-weight: 600; margin-top: 0.5rem; color: var(--text-primary);">Human Handover</div>
                        <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 0.25rem;">Smart transfer to live agent when needed</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
window.APP_BASE = '<?= baseUrl(); ?>';

// Delete Bot
function deleteBot(botId, botName) {
    Swal.fire({
        title: 'Delete Bot?',
        html: `Are you sure you want to delete <strong>${botName}</strong>? This will remove all conversations and training data.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('<?= baseUrl('api/ai-bot/delete.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= CSRF::generateToken(); ?>' },
                body: JSON.stringify({ bot_id: botId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('bot-card-' + botId)?.remove();
                    Swal.fire('Deleted!', 'Bot has been deleted.', 'success');
                    if (!document.querySelector('[id^="bot-card-"]')) location.reload();
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete bot.', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Network error occurred.', 'error'));
        }
    });
}

// Toggle Bot Status
function toggleBotStatus(botId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    fetch('<?= baseUrl('api/ai-bot/toggle-status.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= CSRF::generateToken(); ?>' },
        body: JSON.stringify({ bot_id: botId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Status Updated', text: `Bot is now ${newStatus}.`, timer: 1500, showConfirmButton: false });
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error', data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Network error occurred.', 'error'));
}

// Clone Bot
function cloneBot(botId, botName) {
    Swal.fire({
        title: 'Clone Bot?',
        html: `Create a copy of <strong>${botName}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Clone',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('<?= baseUrl('api/ai-bot/clone.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= CSRF::generateToken(); ?>' },
                body: JSON.stringify({ bot_id: botId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Cloned!', 'Bot has been cloned successfully.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Failed to clone bot.', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Network error occurred.', 'error'));
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
