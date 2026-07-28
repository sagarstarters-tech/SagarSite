<?php
/**
 * WAPI SaaS - User Templates Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireWhatsAppSetup();

error_reporting(E_ALL);
ini_set('display_errors', 1);


$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            'user_id' => $userId,
            'name' => sanitize($_POST['name']),
            'category' => sanitize($_POST['category']),
            'language' => sanitize($_POST['language'] ?? 'en'),
            'header_type' => sanitize($_POST['header_type'] ?? 'none'),
            'header_content' => sanitize($_POST['header_content'] ?? ''),
            'body' => sanitize($_POST['body_content']),
            'variables' => trim($_POST['variables'] ?? '') === '' ? null : sanitize($_POST['variables']),
            'buttons' => trim($_POST['buttons'] ?? '') === '' ? null : (is_array(json_decode($_POST['buttons'], true)) ? $_POST['buttons'] : json_encode(array_map('trim', explode(',', $_POST['buttons'])))),
            'footer' => sanitize($_POST['footer_content'] ?? ''),
            'status' => 'pending'
        ];
        $id = sanitizeInt($_POST['template_id'] ?? 0);
        if ($id > 0) {
            $db->update('templates', $data, 'id = ? AND user_id = ?', [$id, $userId]);
        } else {
            $db->insert('templates', $data);
        }
        setFlash('success', 'Template saved.');
    } elseif ($action === 'delete') {
        $db->delete('templates', 'id = ? AND user_id = ?', [sanitizeInt($_POST['template_id']), $userId]);
        setFlash('success', 'Template deleted.');
    } elseif ($action === 'sync') {
        $waAccount = $db->fetch("SELECT waba_id, access_token FROM whatsapp_accounts WHERE user_id = ? LIMIT 1", [$userId]);
        if (!$waAccount || empty($waAccount['waba_id']) || empty($waAccount['access_token'])) {
            setFlash('danger', 'WhatsApp API is not connected or WABA ID is missing. Setup your API credentials first.');
        } else {
            $wabaId = $waAccount['waba_id'];
            $accessToken = $waAccount['access_token'];
            $url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    $syncedCount = 0;
                    foreach ($data['data'] as $tpl) {
                        $name = sanitize($tpl['name']);
                        $language = sanitize($tpl['language']);
                        $category = sanitize(strtolower($tpl['category'] ?? ''));
                        $status = sanitize(strtolower($tpl['status'] ?? 'pending'));
                        
                        $headerContent = '';
                        $headerType = 'none';
                        $bodyContent = '';
                        $footerContent = '';
                        $variablesContent = null;
                        $buttonsContent = null;
                        
                        if (!empty($tpl['components'])) {
                            foreach ($tpl['components'] as $comp) {
                                if ($comp['type'] === 'HEADER') {
                                    $headerType = sanitize(strtolower($comp['format'] ?? 'text'));
                                    $headerContent = sanitize($comp['text'] ?? '');
                                } elseif ($comp['type'] === 'BODY') {
                                    $bodyContent = sanitize($comp['text'] ?? '');
                                } elseif ($comp['type'] === 'FOOTER') {
                                    $footerContent = sanitize($comp['text'] ?? '');
                                } elseif ($comp['type'] === 'BUTTONS') {
                                    $buttonsContent = is_array($comp['buttons'] ?? null) ? json_encode($comp['buttons']) : null;
                                }
                            }
                        }
                        
                        $existing = $db->fetch("SELECT id FROM templates WHERE user_id = ? AND name = ? AND language = ?", [$userId, $name, $language]);
                        
                        $tplData = [
                            'user_id' => $userId,
                            'name' => $name,
                            'category' => $category,
                            'language' => $language,
                            'header_type' => $headerType,
                            'header_content' => $headerContent,
                            'body' => $bodyContent,
                            'variables' => $variablesContent,
                            'footer' => $footerContent,
                            'buttons' => $buttonsContent,
                            'status' => $status
                        ];
                        
                        try {
                            if ($existing) {
                                $db->update('templates', $tplData, 'id = ?', [$existing['id']]);
                            } else {
                                $db->insert('templates', $tplData);
                            }
                            $syncedCount++;
                        } catch (Exception $ex) {
                            file_put_contents(__DIR__ . '/../sync_error.txt', json_encode($tplData) . "\n" . $ex->getMessage() . "\n" . $ex->getTraceAsString());
                            throw $ex; // re-throw so it 500s or stops
                        }
                    }
                    setFlash('success', "Successfully synced $syncedCount templates from Meta.");
                } else {
                    setFlash('danger', 'Received an invalid response from Meta API.');
                }
            } else {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Unknown Meta API error';
                setFlash('danger', "Failed to sync templates: $errorMsg");
            }
        }
    }
    redirect('dashboard/templates.php');
}

$templates = $db->fetchAll("SELECT * FROM templates WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

$pageTitle = 'Templates';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Message Templates</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Templates</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <form method="POST" style="margin: 0; display: inline-block;">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="action" value="sync">
                    <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-arrow-repeat"></i> Sync from Meta</button>
                </form>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus-lg"></i> Create Template</button>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?>"><?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <h5>No Templates Yet</h5>
                    <p>Create your first message template to get started.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus-lg"></i> Create Template</button>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($templates as $t): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0"><?= e($t['name']); ?></h6>
                            <span class="status-badge status-<?= $t['status'] === 'approved' ? 'active' : ($t['status'] === 'rejected' ? 'failed' : 'pending'); ?>"><?= ucfirst($t['status']); ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($t['category']); ?></span>
                            <span class="badge-custom" style="background: var(--bg-secondary); color: var(--text-secondary);"><?= strtoupper($t['language']); ?></span>
                        </div>
                        <p style="font-size: 0.875rem; color: var(--text-secondary); min-height: 40px;"><?= e(substr($t['body'], 0, 100)); ?><?= strlen($t['body']) > 100 ? '...' : ''; ?></p>
                        <div class="d-flex gap-2 mt-auto">
                            <button class="btn btn-outline-primary btn-sm" onclick="editTemplate(<?= htmlspecialchars(json_encode($t)); ?>)"><i class="bi bi-pencil"></i> Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                                <?= CSRF::tokenField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="template_id" value="<?= $t['id']; ?>">
                                <button class="btn btn-sm" style="color: var(--danger);"><i class="bi bi-trash3"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="template_id" id="tplId" value="0">
                <div class="modal-header"><h5 class="modal-title" id="tplModalTitle">Create Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Template Name *</label><input type="text" name="name" id="tplName" class="form-control" required placeholder="order_confirmation"></div>
                        <div class="col-md-3"><label class="form-label">Category</label>
                            <select name="category" id="tplCategory" class="form-control">
                                <option value="marketing">Marketing</option>
                                <option value="utility">Utility</option>
                                <option value="authentication">Authentication</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Language</label>
                            <select name="language" id="tplLang" class="form-control">
                                <option value="en">English</option>
                                <option value="hi">Hindi</option>
                                <option value="es">Spanish</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Body Content *</label><textarea name="body_content" id="tplBody" class="form-control" rows="4" required placeholder="Hello {{1}}, your order {{2}} has been confirmed!"></textarea><small class="text-muted">Use {{1}}, {{2}}, etc. for variables</small></div>
                        <div class="col-md-6"><label class="form-label">Variables (optional)</label><input type="text" name="variables" id="tplVariables" class="form-control" placeholder="e.g. name, order_number (comma separated)"></div>
                        <div class="col-md-6"><label class="form-label">Buttons (optional)</label><input type="text" name="buttons" id="tplButtons" class="form-control" placeholder="e.g. Visit Website, Call Now (comma separated)"></div>
                        <div class="col-md-6"><label class="form-label">Header (optional)</label><input type="text" name="header_content" id="tplHeader" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Footer (optional)</label><input type="text" name="footer_content" id="tplFooter" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(t) {
    document.getElementById('tplId').value = t.id;
    document.getElementById('tplModalTitle').textContent = 'Edit Template';
    document.getElementById('tplName').value = t.name;
    document.getElementById('tplCategory').value = t.category;
    document.getElementById('tplLang').value = t.language;
    document.getElementById('tplBody').value = t.body;
    document.getElementById('tplVariables').value = t.variables ? (Array.isArray(t.variables) ? t.variables.join(', ') : t.variables.replace(/[\[\]"]/g, '')) : '';
    let btnStr = '';
    if (t.buttons) {
        try {
            let parsed = JSON.parse(t.buttons);
            if (Array.isArray(parsed)) {
                // If it's an array of meta buttons like {type: 'URL', text: 'Visit'}
                if (parsed.length > 0 && typeof parsed[0] === 'object') {
                    btnStr = parsed.map(b => b.text).join(', ');
                } else {
                    btnStr = parsed.join(', ');
                }
            } else {
                btnStr = t.buttons;
            }
        } catch(e) { btnStr = t.buttons; }
    }
    document.getElementById('tplButtons').value = btnStr;
    document.getElementById('tplHeader').value = t.header_content || '';
    document.getElementById('tplFooter').value = t.footer || '';
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
