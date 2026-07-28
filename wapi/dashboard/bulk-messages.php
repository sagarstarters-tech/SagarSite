<?php
/**
 * WAPI SaaS - Bulk Messages Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireWhatsAppSetup();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {

    // ── AJAX: Sync Templates ──────────────────────────────────────────
    if (isAjax() && ($_POST['action'] ?? '') === 'sync_templates') {
        try {
            $wa = new WhatsApp();
            jsonResponse($wa->syncTemplates($userId));
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
        }
    }

    // ── AJAX: Get Contacts List ───────────────────────────────────────
    if (isAjax() && ($_POST['action'] ?? '') === 'get_contacts') {
        $target  = sanitize($_POST['target'] ?? 'all');
        $phones  = [];

        if ($target === 'all') {
            $rows = $db->fetchAll("SELECT phone FROM contacts WHERE user_id = ? AND is_active = 1", [$userId]);
            foreach ($rows as $r) $phones[] = $r['phone'];
        } elseif ($target === 'tag') {
            $tag  = sanitize($_POST['tag'] ?? '');
            $rows = $db->fetchAll("SELECT phone FROM contacts WHERE user_id = ? AND is_active = 1 AND tags LIKE ?", [$userId, "%{$tag}%"]);
            foreach ($rows as $r) $phones[] = $r['phone'];
        } elseif ($target === 'custom') {
            $numbers = array_filter(array_map('trim', explode("\n", $_POST['numbers'] ?? '')));
            $phones  = array_values($numbers);
        }

        // For templates, also return the template name, language and header info from DB
        $templateName = '';
        $templateLanguage = 'en';
        $templateHeaderType = 'none';
        if (!empty($_POST['template_id'])) {
            $tpl = $db->fetch("SELECT name, language, header_type FROM templates WHERE id = ? AND user_id = ?",
                [sanitizeInt($_POST['template_id']), $userId]);
            if ($tpl) {
                $templateName = $tpl['name'];
                $templateLanguage = $tpl['language'];
                $templateHeaderType = $tpl['header_type'] ?? 'none';
            }
        }

        jsonResponse(['success' => true, 'phones' => $phones, 'total' => count($phones), 'template_name' => $templateName, 'template_language' => $templateLanguage, 'template_header_type' => $templateHeaderType]);
    }
}

$totalContacts = $db->count('contacts', 'user_id = ? AND is_active = 1', [$userId]);
$templates     = $db->fetchAll("SELECT id, name, language, body, header_type, header_content FROM templates WHERE user_id = ? AND status = 'approved' ORDER BY name ASC", [$userId]);

// Pre-process variable counts for each template
$templateVarCounts = [];
$templateButtonVars = [];
foreach ($templates as $tpl) {
    preg_match_all('/\{\{(\d+)\}\}/', $tpl['body'], $matches);
    $maxVar = !empty($matches[1]) ? max(array_map('intval', $matches[1])) : 0;
    $templateVarCounts[$tpl['id']] = $maxVar;

    $btnVarCount = 0;
    if (!empty($tpl['buttons'])) {
        $btns = json_decode($tpl['buttons'], true);
        if (is_array($btns)) {
            foreach ($btns as $btn) {
                if (($btn['type'] ?? '') === 'URL' && strpos($btn['url'] ?? '', '{{1}}') !== false) {
                    $btnVarCount++;
                }
            }
        }
    }
    $templateButtonVars[$tpl['id']] = $btnVarCount;
}

$tags    = $db->fetchAll("SELECT DISTINCT tags FROM contacts WHERE user_id = ? AND tags != ''", [$userId]);
$allTags = [];
foreach ($tags as $t) {
    foreach (explode(',', $t['tags']) as $tag) {
        $tag = trim($tag);
        if ($tag && !in_array($tag, $allTags)) $allTags[] = $tag;
    }
}

$csrfToken = CSRF::generateToken();

$pageTitle = 'Bulk Messages';
$extraCss  = [asset('assets/css/dashboard.css')];
$extraJs   = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Bulk Messages</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Bulk Messages</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <button type="button" class="btn btn-outline-success btn-sm" id="syncTemplatesBtn" onclick="syncTemplates()">
                    <i class="bi bi-arrow-repeat"></i> Sync from Meta
                </button>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?>"><?= e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if (!$waAccount): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> <span>Please <a href="<?= baseUrl('dashboard/whatsapp.php'); ?>" class="fw-bold alert-link">Configure WhatsApp API</a> first.</span></div>
        <?php endif; ?>

        <!-- Progress Card (hidden until send starts) -->
        <div class="card mb-4" id="progressCard" style="display:none; border-radius: var(--border-radius);">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-send-fill text-primary"></i> Sending Bulk Messages...</h6>
                <div class="d-flex justify-content-between mb-1">
                    <span id="progressLabel" class="small text-muted">Preparing...</span>
                    <span id="progressCount" class="small fw-bold">0 / 0</span>
                </div>
                <div class="progress mb-3" style="height: 10px; border-radius: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progressBar" style="width: 0%;" role="progressbar"></div>
                </div>
                <div id="progressStats" class="d-flex gap-3 small">
                    <span class="text-success"><i class="bi bi-check-circle-fill"></i> Sent: <strong id="statSent">0</strong></span>
                    <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Failed: <strong id="statFailed">0</strong></span>
                </div>
                <div id="progressErrors" class="mt-3" style="display:none;">
                    <details>
                        <summary class="text-danger small fw-bold">View Errors</summary>
                        <ul id="errorList" class="small text-danger mt-2"></ul>
                    </details>
                </div>
                <div id="progressDone" class="alert alert-success mt-3" style="display:none;">
                    <i class="bi bi-check-circle-fill"></i> 
                    <span><strong>Done!</strong> Bulk send complete. <a href="" class="alert-link ms-2 fw-bold">Refresh</a></span>
                </div>
            </div>
        </div>

        <div class="card" style="border-radius: var(--border-radius);" id="bulkFormCard">
            <div class="card-body p-4">
                <form id="bulkForm" enctype="multipart/form-data">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Target Audience</label>
                            <select name="target" class="form-control" id="bulkTarget" onchange="toggleBulkTarget()">
                                <option value="all">All Contacts (<?= $totalContacts; ?>)</option>
                                <option value="tag">By Tag</option>
                                <option value="custom">Custom Numbers</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="tagGroup" style="display:none;">
                            <label class="form-label fw-bold">Select Tag</label>
                            <select name="tag" class="form-control">
                                <?php foreach ($allTags as $tag): ?>
                                <option value="<?= e($tag); ?>"><?= e($tag); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="numbersGroup" style="display:none;">
                            <label class="form-label fw-bold">Phone Numbers (one per line)</label>
                            <textarea name="numbers" id="customNumbers" class="form-control" rows="5" placeholder="+919876543210&#10;+919876543211&#10;+919876543212"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Message Type</label>
                            <select name="message_type" id="msgType" class="form-control" onchange="toggleMessageType()">
                                <option value="text">📝 Text</option>
                                <option value="image">🖼️ Image</option>
                                <option value="template">📋 Template</option>
                            </select>
                        </div>
                        <div class="col-12" id="metaPolicyWarning" style="display:none;">
                            <div class="alert alert-warning mb-0 py-2" style="font-size: 0.85rem;">
                                <span>
                                    <strong>⚠️ Meta Policy Warning:</strong> Free-form Text and Image messages will <strong>FAIL</strong> unless the contact has messaged you within the last 24 hours. For bulk promotional broadcasts, you <strong>MUST</strong> use an approved <a href="templates.php" class="alert-link">Template</a>.
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6" id="mediaGroup" style="display:none;">
                            <label class="form-label fw-bold">Upload Media</label>
                            <input type="file" class="form-control" accept="image/*,video/*,application/pdf" onchange="uploadGeneralMedia(this, 'bulkMediaUrl')">
                            <input type="hidden" name="media_url" id="bulkMediaUrl">
                            <small class="text-muted">Select a file to upload and send.</small>
                        </div>
                        <div class="col-md-6" id="templateGroup" style="display:none;">
                            <label class="form-label fw-bold">Select Template</label>
                            <select name="template_id" id="templateId" class="form-control" onchange="updateTemplatePreview()">
                                <option value="">-- Choose Template --</option>
                                <?php foreach ($templates as $tpl): ?>
                                <option value="<?= $tpl['id']; ?>"
                                        data-body="<?= e($tpl['body']); ?>"
                                        data-vars="<?= $templateVarCounts[$tpl['id']]; ?>"
                                        data-btn-vars="<?= $templateButtonVars[$tpl['id']] ?? 0; ?>"
                                        data-name="<?= e($tpl['name']); ?>"
                                        data-language="<?= e($tpl['language']); ?>"
                                        data-header-type="<?= e($tpl['header_type'] ?? 'none'); ?>">
                                    <?= e($tpl['name']); ?> (<?= e($tpl['language']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="templateHeaderGroup" style="display:none;">
                            <label class="form-label fw-bold">📷 Template Header Media</label>
                            <input type="file" class="form-control" id="templateHeaderFile" accept="image/*,video/*,application/pdf" onchange="uploadGeneralMedia(this, 'templateHeaderUrl')">
                            <input type="hidden" id="templateHeaderUrl">
                            <small class="text-muted" id="templateHeaderHint" style="display:block; margin-top: 5px;">This template requires a header media. Please select a file to upload.</small>
                        </div>
                        <div class="col-12" id="templateVarsGroup" style="display:none;">
                            <label class="form-label fw-bold">Template Variables</label>
                            <div id="templateVarsContainer"></div>
                            <small class="text-muted">Fill in the values for each <code>{{1}}</code>, <code>{{2}}</code>, etc. placeholder.</small>
                        </div>
                        <div class="col-12" id="templatePreviewGroup" style="display:none;">
                            <label class="form-label fw-bold">Template Preview</label>
                            <div id="templatePreviewBox" style="
                                background: #f0fdf4; border: 1px solid #86efac;
                                border-left: 4px solid #22c55e; border-radius: 8px;
                                padding: 12px 16px; font-size: 0.9rem; color: #166534;
                                min-height: 60px; white-space: pre-wrap; line-height: 1.6;">
                                <span class="text-muted fst-italic">Select a template to see its content...</span>
                            </div>
                        </div>
                        <div class="col-12" id="contentGroup">
                            <label class="form-label fw-bold">Message Content</label>
                            <textarea name="content" id="msgContent" class="form-control" rows="5" placeholder="Type your message here..."></textarea>
                        </div>
                    </div>

                    <button type="button" class="btn btn-success btn-lg mt-4" id="sendBulkBtn"
                        <?= !$waAccount ? 'disabled' : ''; ?>
                        onclick="startBulkSend()">
                        <i class="bi bi-megaphone-fill"></i> Send Bulk Messages
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
const CSRF_TOKEN   = '<?= $csrfToken; ?>';
const BATCH_SIZE   = 10; // contacts per request
const BATCH_URL    = '<?= baseUrl('api/bulk-send-batch.php'); ?>';
const PAGE_URL     = '<?= baseUrl('dashboard/bulk-messages.php'); ?>';

// ── UI toggles ──────────────────────────────────────────────────────────────
function toggleBulkTarget() {
    const target = document.getElementById('bulkTarget').value;
    document.getElementById('tagGroup').style.display    = target === 'tag'    ? 'block' : 'none';
    document.getElementById('numbersGroup').style.display = target === 'custom' ? 'block' : 'none';
}

function toggleMessageType() {
    const type = document.getElementById('msgType').value;
    document.getElementById('mediaGroup').style.display          = (type === 'image')    ? 'block' : 'none';
    document.getElementById('templateGroup').style.display       = (type === 'template') ? 'block' : 'none';
    document.getElementById('templatePreviewGroup').style.display= (type === 'template') ? 'block' : 'none';
    document.getElementById('templateVarsGroup').style.display   = 'none';
    document.getElementById('contentGroup').style.display        = (type === 'template') ? 'none'  : 'block';
    document.getElementById('metaPolicyWarning').style.display   = (type === 'text' || type === 'image') ? 'block' : 'none';
    document.getElementById('msgContent').toggleAttribute('required', type !== 'template');
}

document.addEventListener('DOMContentLoaded', toggleMessageType);

function updateTemplatePreview() {
    const select       = document.getElementById('templateId');
    const option       = select.options[select.selectedIndex];
    const previewBox   = document.getElementById('templatePreviewBox');
    const varsGroup    = document.getElementById('templateVarsGroup');
    const varsContainer= document.getElementById('templateVarsContainer');
    const headerGroup  = document.getElementById('templateHeaderGroup');
    const headerHint   = document.getElementById('templateHeaderHint');

    if (option && option.value) {
        const body       = option.getAttribute('data-body');
        const varCount   = parseInt(option.getAttribute('data-vars')) || 0;
        const btnVarCount= parseInt(option.getAttribute('data-btn-vars')) || 0;
        const headerType = option.getAttribute('data-header-type') || 'none';
        document.getElementById('msgContent').value = body;
        previewBox.textContent = body || 'No content.';

        // Handle header media (image/video/document)
        if (['image', 'video', 'document'].includes(headerType)) {
            headerGroup.style.display = 'block';
            const labels = { image: '📷 This template requires a header image.', video: '🎬 This template requires a header video.', document: '📄 This template requires a header document.' };
            headerHint.textContent = labels[headerType] || 'Please select a file to upload.';
        } else {
            headerGroup.style.display = 'none';
        }

        // Handle body and button variables
        varsContainer.innerHTML = '';
        if (varCount > 0 || btnVarCount > 0) {
            varsGroup.style.display = 'block';
            for (let i = 1; i <= varCount; i++) {
                const d = document.createElement('div');
                d.className = 'mb-2';
                d.innerHTML = `<label class="form-label small fw-semibold text-muted mb-1">Body Variable {{${i}}}</label>
                    <input type="text" name="tpl_vars[]" class="form-control" placeholder="Value for {{${i}}}" required>`;
                varsContainer.appendChild(d);
            }
            for (let i = 1; i <= btnVarCount; i++) {
                const d = document.createElement('div');
                d.className = 'mb-2';
                d.innerHTML = `<label class="form-label small fw-semibold text-muted mb-1">Button Dynamic Link Variable</label>
                    <input type="text" name="btn_vars[]" class="form-control" placeholder="e.g. your-promo-code" required>`;
                varsContainer.appendChild(d);
            }
        } else {
            varsGroup.style.display = 'none';
        }
    } else {
        previewBox.innerHTML = '<span class="text-muted fst-italic">Select a template to see its content...</span>';
        varsGroup.style.display = 'none';
        headerGroup.style.display = 'none';
        varsContainer.innerHTML = '';
    }
}

// ── Media Upload ─────────────────────────────────────────────────────────────
async function uploadGeneralMedia(input, targetHiddenId) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const hintLabel = input.nextElementSibling.nextElementSibling; // the <small> tag
    const originalHint = hintLabel.textContent;
    
    hintLabel.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> Uploading file to server... please wait.';
    input.disabled = true;
    document.getElementById('sendBulkBtn').disabled = true;
    
    const fd = new FormData();
    fd.append('media', file);
    fd.append('_csrf_token', CSRF_TOKEN);
    
    try {
        const r = await fetch('<?= baseUrl('api/upload-media.php') ?>', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            document.getElementById(targetHiddenId).value = d.url;
            hintLabel.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> File uploaded successfully! Ready to send.</span>';
        } else {
            hintLabel.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Upload failed: ' + d.message + '</span>';
            document.getElementById(targetHiddenId).value = '';
            input.value = '';
        }
    } catch (e) {
        hintLabel.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Upload error. Please try again.</span>';
        document.getElementById(targetHiddenId).value = '';
        input.value = '';
    }
    
    input.disabled = false;
    document.getElementById('sendBulkBtn').disabled = false;
}

// ── Sync Templates ───────────────────────────────────────────────────────────
async function syncTemplates() {
    const btn = document.getElementById('syncTemplatesBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    btn.disabled  = true;
    const fd = new FormData();
    fd.append('action', 'sync_templates');
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch('', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const d = await r.json();
        alert(d.message);
        if (d.success) location.reload();
    } catch(e) { alert('Network error during sync.'); }
    btn.innerHTML = orig;
    btn.disabled  = false;
}

// ── Bulk Send Engine ─────────────────────────────────────────────────────────
async function startBulkSend() {
    const form    = document.getElementById('bulkForm');
    const type    = document.getElementById('msgType').value;
    const content = document.getElementById('msgContent').value;
    const target  = document.getElementById('bulkTarget').value;

    // Validation
    if (type !== 'template' && !content.trim()) {
        alert('Please enter a message.');
        return;
    }
    if (type === 'template' && !document.getElementById('templateId').value) {
        alert('Please select a template.');
        return;
    }
    if (!confirm('Send messages to all selected contacts?')) return;

    // Collect template components (header + body + buttons)
    let templateComponents = [];
    if (type === 'template') {
        const selectedOpt = document.getElementById('templateId').selectedOptions[0];
        const headerType  = selectedOpt?.getAttribute('data-header-type') || 'none';
        const headerUrl   = document.getElementById('templateHeaderUrl')?.value || '';

        if (['image', 'video', 'document'].includes(headerType) && !headerUrl) {
            alert('This template requires a Header Media file. Please select and upload a file before sending.');
            return;
        }

        // Header component (image/video/document)
        if (['image', 'video', 'document'].includes(headerType) && headerUrl) {
            const headerParam = { type: headerType };
            headerParam[headerType] = { link: headerUrl };
            templateComponents.push({ type: 'header', parameters: [headerParam] });
        }

        // Body variables
        const varInputs = form.querySelectorAll('input[name="tpl_vars[]"]');
        if (varInputs.length > 0) {
            const params = Array.from(varInputs).map(i => ({ type: 'text', text: i.value }));
            templateComponents.push({ type: 'body', parameters: params });
        }

        // Button variables
        const btnInputs = form.querySelectorAll('input[name="btn_vars[]"]');
        if (btnInputs.length > 0) {
            Array.from(btnInputs).forEach((inp, i) => {
                templateComponents.push({
                    type: 'button',
                    sub_type: 'url',
                    index: i.toString(),
                    parameters: [{ type: 'text', text: inp.value }]
                });
            });
        }
    }

    // Step 1: Fetch contact list from server
    const fd1 = new FormData();
    fd1.append('action',      'get_contacts');
    fd1.append('_csrf_token', CSRF_TOKEN);
    fd1.append('target',      target);
    fd1.append('tag',         document.querySelector('[name="tag"]')?.value || '');
    fd1.append('numbers',     document.getElementById('customNumbers')?.value || '');
    fd1.append('template_id', document.getElementById('templateId')?.value || '');

    let phones = [], templateName = '', templateLanguage = 'en';
    try {
        const r   = await fetch('', { method: 'POST', body: fd1, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const d   = await r.json();
        if (!d.success || d.total === 0) { alert('No contacts found.'); return; }
        phones           = d.phones;
        templateName     = d.template_name || content;
        templateLanguage = d.template_language || 'en';
    } catch(e) { alert('Error fetching contacts: ' + e.message); return; }

    // Step 2: Show progress UI
    document.getElementById('bulkFormCard').style.display = 'none';
    const progressCard = document.getElementById('progressCard');
    progressCard.style.display = 'block';
    progressCard.scrollIntoView({ behavior: 'smooth' });

    const total  = phones.length;
    let sent = 0, failed = 0, allErrors = [];

    const setProgress = (done) => {
        const pct = Math.round((done / total) * 100);
        document.getElementById('progressBar').style.width  = pct + '%';
        document.getElementById('progressCount').textContent = `${done} / ${total}`;
        document.getElementById('progressLabel').textContent = `Sending batch... (${pct}%)`;
        document.getElementById('statSent').textContent   = sent;
        document.getElementById('statFailed').textContent = failed;
    };

    setProgress(0);

    // Step 3: Send in batches
    const mediaUrl = document.getElementById('bulkMediaUrl')?.value || '';

    for (let i = 0; i < phones.length; i += BATCH_SIZE) {
        const batch = phones.slice(i, i + BATCH_SIZE);
        const fd2   = new FormData();
        fd2.append('_csrf_token',         CSRF_TOKEN);
        fd2.append('type',                type);
        fd2.append('content',             type === 'template' ? templateName : content);
        fd2.append('media_url',           mediaUrl);
        fd2.append('phones',              JSON.stringify(batch));
        fd2.append('template_components', JSON.stringify(templateComponents));
        fd2.append('template_language',   templateLanguage);

        try {
            const r = await fetch(BATCH_URL, { method: 'POST', body: fd2 });
            const d = await r.json();
            sent   += d.sent   || 0;
            failed += d.failed || 0;
            if (d.errors && d.errors.length) allErrors.push(...d.errors);
        } catch(e) {
            failed += batch.length;
            allErrors.push('Batch error: ' + e.message);
        }

        setProgress(Math.min(i + BATCH_SIZE, total));
        // Small pause between batches to be kind to the server
        await new Promise(r => setTimeout(r, 200));
    }

    // Step 4: Done
    document.getElementById('progressLabel').textContent = 'Complete!';
    document.getElementById('progressBar').classList.remove('progress-bar-animated', 'progress-bar-striped');
    document.getElementById('progressDone').style.display = 'block';

    if (allErrors.length) {
        const errDiv  = document.getElementById('progressErrors');
        const errList = document.getElementById('errorList');
        errDiv.style.display = 'block';
        allErrors.forEach(e => {
            const li = document.createElement('li');
            li.textContent = e;
            errList.appendChild(li);
        });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
