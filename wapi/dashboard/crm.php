<?php
/**
 * WAPI SaaS - WhatsApp CRM (Sales Pipeline)
 */
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/session.php';
    Auth::requireLogin();

    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];

    $hideNav = true;

    // Define CRM Stages
    $stages = [
        'Lead' => ['color' => '#64748b', 'icon' => 'bi-person-plus'],
        'Contacted' => ['color' => '#3b82f6', 'icon' => 'bi-chat-left-dots'],
        'Qualified' => ['color' => '#8b5cf6', 'icon' => 'bi-check-circle'],
        'Proposal' => ['color' => '#f59e0b', 'icon' => 'bi-file-earmark-text'],
        'Won' => ['color' => '#10b981', 'icon' => 'bi-trophy']
    ];

    // Handle status update (move card)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_status' && !empty($_POST['contact_id']) && !empty($_POST['status'])) {
            $db->update('contacts', [
                'status' => sanitize($_POST['status'])
            ], 'id = ? AND user_id = ?', [sanitizeInt($_POST['contact_id']), $userId]);
            setFlash('success', 'Status updated.');
        }
        redirect('dashboard/crm.php');
    }

    // Get contacts grouped by status
    $allContacts = $db->fetchAll("SELECT id, name, phone, company, status, estimated_value, tags FROM contacts WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC", [$userId]);

    $pipeline = [];
    foreach ($stages as $stage => $config) {
        $pipeline[$stage] = [];
    }
    foreach ($allContacts as $c) {
        $status = $c['status'] ?: 'Lead';
        if (isset($pipeline[$status])) {
            $pipeline[$status][] = $c;
        } else {
            $pipeline['Lead'][] = $c; // Fallback
        }
    }

    $pageTitle = 'WhatsApp CRM';
    $extraCss = [asset('assets/css/dashboard.css')];
    include __DIR__ . '/../includes/header.php';
?>

<style>
.kanban-board {
    display: flex;
    gap: 1.25rem;
    overflow-x: auto;
    padding-bottom: 1rem;
    min-height: calc(100vh - 200px);
}

.kanban-column {
    flex: 0 0 280px;
    background: var(--bg-secondary);
    border-radius: var(--border-radius);
    display: flex;
    flex-direction: column;
    max-height: 100%;
}

.kanban-column-header {
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid var(--border-color);
}

.kanban-column-title {
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.kanban-column-count {
    background: var(--border-color);
    color: var(--text-secondary);
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.kanban-cards {
    padding: 0.75rem;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.kanban-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-sm);
    padding: 1rem;
    box-shadow: var(--shadow-sm);
    transition: var(--transition-fast);
    cursor: grab;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.kanban-card-title {
    font-weight: 700;
    font-size: 0.9375rem;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.kanban-card-subtitle {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
}

.kanban-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}

.kanban-card-value {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.875rem;
}

.kanban-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.kanban-card-tag {
    font-size: 0.625rem;
    padding: 1px 6px;
    border-radius: 4px;
    background: var(--gray-100);
    color: var(--gray-600);
    text-transform: uppercase;
    font-weight: 700;
}

.status-btn {
    padding: 2px 6px;
    font-size: 0.75rem;
    border-radius: 4px;
}
</style>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">WhatsApp CRM</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>CRM Pipeline</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('dashboard/contacts.php'); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul"></i> List View</a>
                <a href="<?= baseUrl('dashboard/contacts.php'); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Lead</a>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="kanban-board">
            <?php foreach ($pipeline as $stage => $contacts): ?>
            <div class="kanban-column">
                <div class="kanban-column-header">
                    <div class="kanban-column-title" style="color: <?= $stages[$stage]['color']; ?>">
                        <i class="bi <?= $stages[$stage]['icon']; ?>"></i>
                        <?= $stage; ?>
                    </div>
                    <span class="kanban-column-count"><?= count($contacts); ?></span>
                </div>
                <div class="kanban-cards">
                    <?php if (empty($contacts)): ?>
                        <div class="text-center py-4 text-muted" style="font-size: 0.8125rem; opacity: 0.5;">Empty</div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                        <div class="kanban-card">
                            <div class="kanban-card-title"><?= e($contact['name'] ?? 'Unknown'); ?></div>
                            <div class="kanban-card-subtitle">
                                <i class="bi bi-building"></i> <?= e(($contact['company'] ?? '') ?: 'No Company'); ?><br>
                                <i class="bi bi-whatsapp"></i> <?= e($contact['phone'] ?? ''); ?>
                            </div>
                            
                            <?php if (!empty($contact['tags'])): ?>
                            <div class="kanban-card-tags">
                                <?php foreach (explode(',', $contact['tags']) as $tag): ?>
                                    <span class="kanban-card-tag"><?= e(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="kanban-card-footer">
                                <div class="kanban-card-value">
                                    ₹<?= number_format((float)($contact['estimated_value'] ?? 0), 0); ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-icon btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bi bi-arrow-right-circle"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li class="dropdown-header">Move to...</li>
                                        <?php foreach ($stages as $s => $cfg): if ($s === $stage) continue; ?>
                                        <li>
                                            <form method="POST">
                                                <?= CSRF::tokenField(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="contact_id" value="<?= $contact['id']; ?>">
                                                <input type="hidden" name="status" value="<?= $s; ?>">
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                                    <i class="bi <?= $cfg['icon']; ?>" style="color: <?= $cfg['color']; ?>"></i> <?= $s; ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-primary" href="<?= baseUrl('dashboard/messages.php?to=' . urlencode($contact['phone'])); ?>"><i class="bi bi-send"></i> Send Message</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php 
    include __DIR__ . '/../includes/footer.php'; 
} catch (Throwable $e) {
    error_log("CRM FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    die("<h1>System Error</h1><p>Error: " . htmlspecialchars($e->getMessage()) . "</p><p>File: " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>");
}
?>

