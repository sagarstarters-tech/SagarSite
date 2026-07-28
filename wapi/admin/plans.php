<?php
/**
 * WAPI SaaS - Admin Plans Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_plan') {
        $planId = sanitizeInt($_POST['plan_id'] ?? 0);
        $data = [
            'name' => sanitize($_POST['name']),
            'slug' => slugify($_POST['name']),
            'description' => sanitize($_POST['description']),
            'monthly_price' => floatval($_POST['monthly_price']),
            'yearly_price' => floatval($_POST['yearly_price']),
            'message_limit' => sanitizeInt($_POST['message_limit']),
            'contacts_limit' => sanitizeInt($_POST['contacts_limit']),
            'api_calls_limit' => sanitizeInt($_POST['api_calls_limit']),
            'templates_limit' => sanitizeInt($_POST['templates_limit']),
            'chatbot_enabled' => isset($_POST['chatbot_enabled']) ? 1 : 0,
            'bulk_messaging' => isset($_POST['bulk_messaging']) ? 1 : 0,
            'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
            'analytics_enabled' => isset($_POST['analytics_enabled']) ? 1 : 0,
            'priority_support' => isset($_POST['priority_support']) ? 1 : 0,
            'badge_color' => sanitize($_POST['badge_color']),
            'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => sanitizeInt($_POST['sort_order'])
        ];

        if ($planId > 0) {
            $db->update('plans', $data, 'id = ?', [$planId]);
            // Update features
            $db->delete('plan_features', 'plan_id = ?', [$planId]);
        } else {
            $planId = $db->insert('plans', $data);
        }

        // Save plan features
        $features = array_filter(explode("\n", trim($_POST['features_list'] ?? '')));
        foreach ($features as $i => $feature) {
            $feature = trim($feature);
            if (empty($feature)) continue;
            $included = 1;
            if (str_starts_with($feature, '-')) {
                $included = 0;
                $feature = trim(substr($feature, 1));
            }
            $db->insert('plan_features', [
                'plan_id' => $planId,
                'feature_text' => $feature,
                'is_included' => $included,
                'sort_order' => $i
            ]);
        }

        setFlash('success', 'Plan saved successfully!');
    } elseif ($action === 'delete') {
        $db->delete('plans', 'id = ?', [sanitizeInt($_POST['plan_id'])]);
        setFlash('success', 'Plan deleted.');
    }
    redirect('admin/plans.php');
}

$plans = $db->fetchAll("SELECT * FROM plans ORDER BY sort_order ASC");
$editPlan = null;
if (isset($_GET['edit'])) {
    $editPlan = $db->fetch("SELECT * FROM plans WHERE id = ?", [sanitizeInt($_GET['edit'])]);
    $editFeatures = $db->fetchAll("SELECT * FROM plan_features WHERE plan_id = ? ORDER BY sort_order", [sanitizeInt($_GET['edit'])]);
}

$pageTitle = 'Plan Management';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Plans</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Plans</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('admin/plans.php?edit=0'); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Plan</a>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['edit'])): ?>
        <!-- Plan Form -->
        <div class="card mb-4" style="border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><?= $editPlan ? 'Edit' : 'Create New'; ?> Plan</h5>
                <form method="POST">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="save_plan">
                    <input type="hidden" name="plan_id" value="<?= $editPlan['id'] ?? 0; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Plan Name</label><input type="text" name="name" class="form-control" value="<?= e($editPlan['name'] ?? ''); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Monthly Price (₹)</label><input type="number" name="monthly_price" class="form-control" step="0.01" value="<?= e($editPlan['monthly_price'] ?? '0'); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Yearly Price (₹)</label><input type="number" name="yearly_price" class="form-control" step="0.01" value="<?= e($editPlan['yearly_price'] ?? '0'); ?>" required></div>
                        <div class="col-12"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="<?= e($editPlan['description'] ?? ''); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Message Limit</label><input type="number" name="message_limit" class="form-control" value="<?= e($editPlan['message_limit'] ?? '1000'); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Contacts Limit</label><input type="number" name="contacts_limit" class="form-control" value="<?= e($editPlan['contacts_limit'] ?? '500'); ?>"></div>
                        <div class="col-md-3"><label class="form-label">API Calls Limit</label><input type="number" name="api_calls_limit" class="form-control" value="<?= e($editPlan['api_calls_limit'] ?? '5000'); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Templates Limit</label><input type="number" name="templates_limit" class="form-control" value="<?= e($editPlan['templates_limit'] ?? '10'); ?>"></div>
                        
                        <div class="col-12">
                            <label class="form-label">Features (one per line, prefix with - for excluded)</label>
                            <textarea name="features_list" class="form-control" rows="6" placeholder="5,000 Messages/month&#10;2,500 Contacts&#10;-Bulk Messaging&#10;-Priority Support"><?php
                            if (isset($editFeatures)) {
                                foreach ($editFeatures as $f) {
                                    echo ($f['is_included'] ? '' : '-') . $f['feature_text'] . "\n";
                                }
                            }
                            ?></textarea>
                        </div>
                        
                        <div class="col-md-3"><label class="form-label">Badge Color</label><input type="color" name="badge_color" class="form-control form-control-color" value="<?= e($editPlan['badge_color'] ?? '#6c63ff'); ?>" style="height:42px;"></div>
                        <div class="col-md-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= e($editPlan['sort_order'] ?? '0'); ?>"></div>
                        
                        <div class="col-12 d-flex flex-wrap gap-4">
                            <?php 
                            $toggles = [
                                'chatbot_enabled' => 'Chatbot',
                                'bulk_messaging' => 'Bulk Messaging',
                                'webhook_enabled' => 'Webhooks',
                                'analytics_enabled' => 'Analytics',
                                'priority_support' => 'Priority Support',
                                'is_popular' => 'Popular Badge',
                                'is_active' => 'Active'
                            ];
                            foreach ($toggles as $key => $label):
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= $key; ?>" id="<?= $key; ?>" <?= ($editPlan[$key] ?? ($key === 'is_active' ? 1 : 0)) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="<?= $key; ?>"><?= $label; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Plan</button>
                        <a href="<?= baseUrl('admin/plans.php'); ?>" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Plans List -->
        <div class="row g-4">
            <?php foreach ($plans as $plan): ?>
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card <?= $plan['is_popular'] ? 'popular' : ''; ?>" style="text-align:left;">
                    <?php if ($plan['is_popular']): ?><div class="pricing-badge">Popular</div><?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0 fw-bold" style="color: <?= e($plan['badge_color']); ?>;"><?= e($plan['name']); ?></h5>
                        <span class="status-badge <?= $plan['is_active'] ? 'status-active' : 'status-inactive'; ?>"><?= $plan['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    </div>
                    <p class="text-muted mb-3" style="font-size: 0.875rem;"><?= e($plan['description']); ?></p>
                    <div class="mb-3">
                        <span class="fw-bold" style="font-size: 1.5rem;"><?= formatCurrency($plan['monthly_price']); ?></span>
                        <span class="text-muted">/mo</span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="fw-bold"><?= formatCurrency($plan['yearly_price']); ?></span>
                        <span class="text-muted">/yr</span>
                    </div>
                    <div class="text-muted mb-3" style="font-size: 0.8125rem;">
                        <?= number_format($plan['message_limit']); ?> messages • <?= number_format($plan['contacts_limit']); ?> contacts
                    </div>
                    <div class="d-flex gap-2">
                        <a href="?edit=<?= $plan['id']; ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this plan?')">
                            <?= CSRF::tokenField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                            <button class="btn btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);"><i class="bi bi-trash3"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
