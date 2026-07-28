<?php
/**
 * WAPI SaaS - Send Message Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireWhatsAppSetup();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

// Get user's WhatsApp account
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Security token mismatch. Please refresh the page.']);
        }
        setFlash('danger', 'Security token mismatch.');
        redirect('dashboard/messages.php');
    }

    if (!$waAccount) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Please configure your WhatsApp API settings first.']);
        }
        setFlash('danger', 'Please configure your WhatsApp API settings first.');
        redirect('dashboard/whatsapp.php');
    }
    
    $to = sanitize($_POST['to'] ?? '');
    $type = sanitize($_POST['message_type'] ?? 'text');
    $content = $_POST['content'] ?? '';
    $mediaUrl = sanitize($_POST['media_url'] ?? '');

    // Handle media file upload (image / video / document)
    $mediaFileKey = match($type) {
        'image'    => 'image_file',
        'video'    => 'video_file',
        'document' => 'document_file',
        default    => null
    };
    $allowedExts = match($type) {
        'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video'    => ['mp4', 'mov', 'avi', 'mkv', '3gp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip'],
        default    => []
    };
    if ($mediaFileKey && isset($_FILES[$mediaFileKey]) && $_FILES[$mediaFileKey]['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES[$mediaFileKey], 'messages', $allowedExts);
        if ($upload['success']) {
            $mediaUrl = baseUrl($upload['path']);
        } else {
            if (isAjax()) jsonResponse(['success' => false, 'message' => 'File upload failed: ' . $upload['message']]);
            setFlash('danger', 'File upload failed: ' . $upload['message']);
            redirect('dashboard/messages.php');
        }
    }

    if (isAjax()) {
        // Handle Sync Templates
        if (($_POST['action'] ?? '') === 'sync_templates') {
            try {
                $wa = new WhatsApp();
                jsonResponse($wa->syncTemplates($userId));
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
            }
        }

        try {
            $wa = new WhatsApp();
            $result = null;

            switch ($type) {
                case 'text': $result = $wa->sendText($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $content); break;
                case 'image': $result = $wa->sendImage($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, $content); break;
                case 'video': $result = $wa->sendVideo($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, $content); break;
                case 'document': $result = $wa->sendDocument($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, sanitize($_POST['filename'] ?? ''), $content); break;
                case 'template':
                    $templateId = sanitizeInt($_POST['template_id'] ?? 0);
                    $tpl = $db->fetch("SELECT name, language FROM templates WHERE id = ? AND user_id = ?", [$templateId, $userId]);
                    if ($tpl) {
                        $templateLanguage = sanitize($_POST['template_language'] ?? $tpl['language']);
                        $templateComponents = [];
                        if (!empty($_POST['template_components'])) {
                            $decoded = json_decode($_POST['template_components'], true);
                            if (is_array($decoded)) $templateComponents = $decoded;
                        }
                        $result = $wa->sendTemplate($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $tpl['name'], $templateLanguage, $templateComponents);
                    } else {
                        $result = ['success' => false, 'message' => 'Invalid template selected.'];
                    }
                    break;
            }

            jsonResponse($result);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
        }
    }
}

// Get contacts for autocomplete
$contacts = $db->fetchAll("SELECT id, name, phone FROM contacts WHERE user_id = ? AND is_active = 1 ORDER BY name ASC LIMIT 100", [$userId]);
$templates = $db->fetchAll("SELECT id, name, language, body, header_type, buttons FROM templates WHERE user_id = ? AND status = 'approved' ORDER BY name ASC", [$userId]);

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

$pageTitle = 'Send Message';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Send Message</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Send Message</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <button type="button" class="btn btn-outline-success btn-sm" id="syncTemplatesBtn" onclick="syncTemplates()">
                    <i class="bi bi-arrow-repeat"></i> Sync from Meta
                </button>
            </div>
        </div>

        <?php if (!$waAccount): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Please <a href="<?= baseUrl('dashboard/whatsapp.php'); ?>" class="fw-bold">configure your WhatsApp API</a> to start sending messages.</div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card" style="border-radius: var(--border-radius);">
                    <div class="card-body p-4">
                        <div id="alertContainer"></div>
                        
                        <form id="sendMessageForm" method="POST" enctype="multipart/form-data">
                            <?= CSRF::tokenField(); ?>
                            
                            <div class="form-group">
                                <label class="form-label">Recipient Phone Number</label>
                                <div class="input-group">
                                    <i class="bi bi-phone input-icon"></i>
                                    <input type="text" name="to" id="msgTo" class="form-control" placeholder="+91 9876543210" required list="contactsList">
                                </div>
                                <datalist id="contactsList">
                                    <?php foreach ($contacts as $c): ?>
                                    <option value="<?= e($c['phone']); ?>"><?= e($c['name']); ?> (<?= e($c['phone']); ?>)</option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Message Type</label>
                                <select name="message_type" id="msgType" class="form-control" onchange="toggleMediaField()">
                                    <option value="text">📝 Text Message</option>
                                    <option value="image">🖼️ Image</option>
                                    <option value="video">🎬 Video</option>
                                    <option value="document">📄 Document</option>
                                    <option value="template">📋 Template Message</option>
                                </select>
                            </div>

                            <div class="form-group" id="templateGroup" style="display: none;">
                                <label class="form-label">Select Template</label>
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

                            <div class="form-group" id="templateHeaderGroup" style="display:none;">
                                <label class="form-label">📷 Template Header Media</label>
                                <input type="file" class="form-control" id="templateHeaderFile" accept="image/*,video/*,application/pdf" onchange="uploadGeneralMedia(this, 'templateHeaderUrl')">
                                <input type="hidden" id="templateHeaderUrl" name="templateHeaderUrl">
                                <small class="text-muted" id="templateHeaderHint" style="display:block; margin-top: 5px;">This template requires a header media. Please select a file to upload.</small>
                            </div>

                            <div class="form-group" id="templateVarsGroup" style="display:none;">
                                <label class="form-label">Template Variables</label>
                                <div id="templateVarsContainer"></div>
                                <small class="text-muted">Fill in the values for each <code>{{1}}</code>, <code>{{2}}</code>, etc. placeholder.</small>
                            </div>

                            <div class="form-group" id="templatePreviewGroup" style="display: none;">
                                <label class="form-label">Template Content</label>
                                <div id="templatePreviewBox" style="
                                    background: #f0fdf4;
                                    border: 1px solid #86efac;
                                    border-left: 4px solid #22c55e;
                                    border-radius: 8px;
                                    padding: 12px 16px;
                                    font-size: 0.9rem;
                                    color: #166534;
                                    min-height: 60px;
                                    white-space: pre-wrap;
                                    line-height: 1.6;
                                ">
                                    <span class="text-muted fst-italic">Select a template to see its content...</span>
                                </div>
                            </div>

                            <div class="form-group" id="mediaUrlGroup" style="display: none;">
                                <label class="form-label">Media URL</label>
                                <input type="url" name="media_url" id="mediaUrl" class="form-control" placeholder="https://example.com/image.jpg">
                                <div class="mt-3 p-3 bg-light rounded-3" id="mediaUploadWrapper" style="display:none;">
                                    <label class="form-label small fw-semibold text-muted mb-1" id="mediaUploadLabel">Or Upload File Instead</label>
                                    <input type="file" name="image_file" id="mediaFileInput" class="form-control">
                                    <small class="text-muted d-block mt-1" id="mediaUploadHint">Supported: JPG, PNG, GIF, WEBP</small>
                                </div>
                            </div>

                            <div class="form-group" id="filenameGroup" style="display: none;">
                                <label class="form-label">Filename</label>
                                <input type="text" name="filename" class="form-control" placeholder="document.pdf">
                            </div>

                            <div class="form-group" id="contentGroup">
                                <label class="form-label">Message / Caption</label>
                                <textarea name="content" id="msgContent" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
                                <small class="text-muted"><span id="charCount">0</span>/4096 characters</small>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100" id="sendBtn" <?= !$waAccount ? 'disabled' : ''; ?>>
                                <i class="bi bi-send-fill"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="col-lg-5">
                <div class="whatsapp-phone">
                    <div class="whatsapp-screen">
                        <div class="wa-header">
                            <div class="wa-avatar"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <div class="wa-name" id="previewName">Recipient</div>
                                <div class="wa-status">WhatsApp</div>
                            </div>
                        </div>
                        <div class="wa-messages" id="previewMessages" style="min-height: 300px;">
                            <div class="text-center text-muted py-5" id="previewPlaceholder" style="font-size: 0.875rem;">
                                <i class="bi bi-chat-dots" style="font-size: 2rem;"></i><br>
                                Your message preview will appear here
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Toggle media field based on message type
function toggleMediaField() {
    const type = document.getElementById('msgType').value;
    const mediaUrlGroup  = document.getElementById('mediaUrlGroup');
    const uploadWrapper  = document.getElementById('mediaUploadWrapper');
    const fileInput      = document.getElementById('mediaFileInput');
    const uploadLabel    = document.getElementById('mediaUploadLabel');
    const uploadHint     = document.getElementById('mediaUploadHint');
    const mediaUrl       = document.getElementById('mediaUrl');

    mediaUrlGroup.style.display = ['image','video','document'].includes(type) ? 'block' : 'none';
    document.getElementById('filenameGroup').style.display = type === 'document' ? 'block' : 'none';
    document.getElementById('templateGroup').style.display = type === 'template' ? 'block' : 'none';
    document.getElementById('templatePreviewGroup').style.display = type === 'template' ? 'block' : 'none';
    
    // Hide header and vars initially when switching to non-template
    if (type !== 'template') {
        document.getElementById('templateHeaderGroup').style.display = 'none';
        document.getElementById('templateVarsGroup').style.display = 'none';
    } else {
        updateTemplatePreview();
    }

    document.getElementById('contentGroup').style.display = type === 'template' ? 'none' : 'block';

    // Configure upload per media type
    const uploadConfig = {
        image:    { name: 'image_file',    accept: 'image/*',  label: 'Or Upload Image Instead', hint: 'Supported: JPG, PNG, GIF, WEBP', placeholder: 'https://example.com/image.jpg' },
        video:    { name: 'video_file',    accept: 'video/*',  label: 'Or Upload Video Instead', hint: 'Supported: MP4, MOV, AVI, MKV', placeholder: 'https://example.com/video.mp4' },
        document: { name: 'document_file', accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip', label: 'Or Upload Document Instead', hint: 'Supported: PDF, DOC, XLS, PPT, TXT, ZIP', placeholder: 'https://example.com/file.pdf' },
    };

    if (uploadConfig[type]) {
        const cfg = uploadConfig[type];
        fileInput.name    = cfg.name;
        fileInput.accept  = cfg.accept;
        uploadLabel.textContent = cfg.label;
        uploadHint.textContent  = cfg.hint;
        mediaUrl.placeholder    = cfg.placeholder;
        uploadWrapper.style.display = 'block';
    } else {
        uploadWrapper.style.display = 'none';
    }

    // Required attribute toggling
    document.getElementById('msgContent').toggleAttribute('required', type !== 'template');
    document.getElementById('templateId').toggleAttribute('required', type === 'template');
    updatePreview();
}

function updateTemplatePreview() {
    const select = document.getElementById('templateId');
    const option = select.options[select.selectedIndex];
    const previewBox = document.getElementById('templatePreviewBox');
    const varsGroup = document.getElementById('templateVarsGroup');
    const varsContainer = document.getElementById('templateVarsContainer');
    const headerGroup = document.getElementById('templateHeaderGroup');
    const headerHint = document.getElementById('templateHeaderHint');

    if (option && option.value) {
        const body = option.getAttribute('data-body');
        const varCount = parseInt(option.getAttribute('data-vars')) || 0;
        const btnVarCount = parseInt(option.getAttribute('data-btn-vars')) || 0;
        const headerType = option.getAttribute('data-header-type') || 'none';

        document.getElementById('msgContent').value = body;
        previewBox.textContent = body || 'No content available for this template.';

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

        updatePreview();
    } else {
        previewBox.innerHTML = '<span class="text-muted fst-italic">Select a template to see its content...</span>';
        varsGroup.style.display = 'none';
        headerGroup.style.display = 'none';
        varsContainer.innerHTML = '';
        if (document.getElementById('msgType').value === 'template') {
            document.getElementById('msgContent').value = '';
            updatePreview();
        }
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
    document.getElementById('sendBtn').disabled = true;
    
    const fd = new FormData();
    fd.append('media', file);
    fd.append('_csrf_token', '<?= CSRF::generateToken(); ?>');
    
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
    document.getElementById('sendBtn').disabled = false;
}

// Sync Templates via AJAX
async function syncTemplates() {
    const btn = document.getElementById('syncTemplatesBtn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'sync_templates');
    formData.append('_csrf_token', '<?= CSRF::generateToken(); ?>');

    try {
        const res = await fetch('', { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const result = await res.json();
        
        if (result.success) {
            showAlert('#alertContainer', 'success', result.message);
            // Refresh the page to show new templates or ideally update dropdown via JS
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('#alertContainer', 'danger', result.message);
        }
    } catch(err) {
        showAlert('#alertContainer', 'danger', 'Network error during sync.');
    }
    btn.innerHTML = originalHtml;
    btn.disabled = false;
}

// Character counter
document.getElementById('msgContent').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
    updatePreview();
});

document.getElementById('msgTo').addEventListener('input', function() {
    document.getElementById('previewName').textContent = this.value || 'Recipient';
});

function updatePreview() {
    const content = document.getElementById('msgContent').value;
    const preview = document.getElementById('previewMessages');
    const placeholder = document.getElementById('previewPlaceholder');
    
    if (content.trim()) {
        placeholder.style.display = 'none';
        let existing = preview.querySelector('.wa-bubble.sent');
        if (!existing) {
            existing = document.createElement('div');
            existing.className = 'wa-bubble sent';
            preview.appendChild(existing);
        }
        const now = new Date();
        existing.innerHTML = '<div>' + escapeHtml(content) + '</div><div class="wa-time">' + now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ' ✓✓</div>';
    } else {
        placeholder.style.display = '';
        const existing = preview.querySelector('.wa-bubble.sent');
        if (existing) existing.remove();
    }
}

// Send message via AJAX
document.getElementById('sendMessageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('sendBtn');
    btn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;display:inline-block;"></span> Sending...';
    btn.disabled = true;

    const formData = new FormData(this);
    
    // Build template components if template type
    const type = document.getElementById('msgType').value;
    if (type === 'template') {
        const selectedOpt = document.getElementById('templateId').selectedOptions[0];
        const headerType  = selectedOpt?.getAttribute('data-header-type') || 'none';
        const headerUrl   = document.getElementById('templateHeaderUrl')?.value || '';
        
        let templateComponents = [];

        if (['image', 'video', 'document'].includes(headerType) && !headerUrl) {
            alert('This template requires a Header Media file. Please select and upload a file before sending.');
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Message';
            btn.disabled = false;
            return;
        }

        if (['image', 'video', 'document'].includes(headerType) && headerUrl) {
            const headerParam = { type: headerType };
            headerParam[headerType] = { link: headerUrl };
            templateComponents.push({ type: 'header', parameters: [headerParam] });
        }

        const formElem = document.getElementById('sendMessageForm');
        const varInputs = formElem.querySelectorAll('input[name="tpl_vars[]"]');
        if (varInputs.length > 0) {
            const params = Array.from(varInputs).map(i => ({ type: 'text', text: i.value }));
            templateComponents.push({ type: 'body', parameters: params });
        }

        const btnInputs = formElem.querySelectorAll('input[name="btn_vars[]"]');
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
        
        if (templateComponents.length > 0) {
            formData.append('template_components', JSON.stringify(templateComponents));
        }
        
        formData.append('template_language', selectedOpt?.getAttribute('data-language') || 'en');
    }

    try {
        const res = await fetch('', { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(e) {
            console.error('Failed to parse JSON:', text);
            showAlert('#alertContainer', 'danger', 'Server returned invalid response. Check console.');
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Message';
            btn.disabled = false;
            return;
        }

        showAlert('#alertContainer', result.success ? 'success' : 'danger', result.message);
        if (result.success) {
            document.getElementById('msgContent').value = '';
            document.getElementById('charCount').textContent = '0';
            updatePreview();
        }
    } catch(err) {
        console.error('Fetch Error:', err);
        showAlert('#alertContainer', 'danger', 'Network error. Please try again.');
    }
    btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Message';
    btn.disabled = false;
});
// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleMediaField();

    // Pre-fill recipient phone number from URL parameter (e.g. from Contacts page "Send Message" action)
    const urlParams = new URLSearchParams(window.location.search);
    const toParam = urlParams.get('to');
    if (toParam) {
        const msgToInput = document.getElementById('msgTo');
        msgToInput.value = toParam;
        document.getElementById('previewName').textContent = toParam;
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
