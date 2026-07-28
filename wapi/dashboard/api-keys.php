<?php
/**
 * WAPI SaaS - API Key Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireActivePlan();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $name = sanitize($_POST['key_name'] ?? 'Default');
        $apiKey = generateApiKey();
        $apiSecret = generateApiSecret();

        $db->insert('api_keys', [
            'user_id' => $userId,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'name' => $name,
            'is_active' => 1
        ]);
        setFlash('success', "API Key generated! Key: {$apiKey}");
    } elseif ($action === 'toggle') {
        $keyId = sanitizeInt($_POST['key_id']);
        $key = $db->fetch("SELECT is_active FROM api_keys WHERE id = ? AND user_id = ?", [$keyId, $userId]);
        if ($key) {
            $db->update('api_keys', ['is_active' => $key['is_active'] ? 0 : 1], 'id = ? AND user_id = ?', [$keyId, $userId]);
            setFlash('success', 'API key status updated.');
        }
    } elseif ($action === 'delete') {
        $db->delete('api_keys', 'id = ? AND user_id = ?', [sanitizeInt($_POST['key_id']), $userId]);
        setFlash('success', 'API key deleted.');
    }
    redirect('dashboard/api-keys.php');
}

$apiKeys = $db->fetchAll("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

$pageTitle = 'API Keys';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">API Keys</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>API Keys</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Generate New Key -->
        <div class="card mb-4" style="border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-key text-primary"></i> Generate New API Key</h5>
                <form method="POST" class="d-flex gap-3 align-items-end flex-wrap">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="generate">
                    <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                        <label class="form-label">Key Name</label>
                        <input type="text" name="key_name" class="form-control" placeholder="My App Key" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Generate Key</button>
                </form>
            </div>
        </div>

        <!-- API Keys List -->
        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title mb-0">Your API Keys (<?= count($apiKeys); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Name</th><th>API Key</th><th>Status</th><th>Last Used</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($apiKeys)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-key" style="font-size: 2rem;"></i><br>No API keys generated yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($apiKeys as $key): ?>
                        <tr>
                            <td class="fw-bold"><?= e($key['name']); ?></td>
                            <td>
                                <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-size: 0.8125rem;" id="key-<?= $key['id']; ?>">
                                    <?= e(substr($key['api_key'], 0, 12)); ?>••••••••
                                </code>
                                <button class="btn btn-sm ms-1" onclick="copyToClipboard('<?= e($key['api_key']); ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                            </td>
                            <td><span class="status-badge status-<?= $key['is_active'] ? 'active' : 'inactive'; ?>"><?= $key['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= $key['last_used_at'] ? timeAgo($key['last_used_at']) : 'Never'; ?></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= formatDate($key['created_at']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form method="POST" style="display:inline;"><?= CSRF::tokenField(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="key_id" value="<?= $key['id']; ?>"><button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);"><i class="bi bi-<?= $key['is_active'] ? 'pause' : 'play'; ?>-circle"></i></button></form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this API key?')"><?= CSRF::tokenField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="key_id" value="<?= $key['id']; ?>"><button class="btn btn-icon btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);"><i class="bi bi-trash3"></i></button></form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- API Usage Guide -->
        <div class="card mt-4" style="border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-code-slash text-primary"></i> Quick Start</h5>
                <pre style="background: var(--gray-900); color: #e2e8f0; padding: 1.5rem; border-radius: 8px; overflow-x: auto; font-size: 0.8125rem;"><code>// Send a WhatsApp message via API
curl -X POST <?= APP_URL; ?>/api/send-message.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+919876543210",
    "type": "text",
    "message": "Hello from WAPI!"
  }'</code></pre>
            </div>
        </div>
    </main>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('#alertContainer', 'success', 'API key copied to clipboard!');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
