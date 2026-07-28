<?php
/**
 * WAPI SaaS - AI ChatBot Editor
 * Multi-tab form to create/edit an AI bot
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];
$hideNav = true;

// Check if editing
$botId = sanitizeInt($_GET['id'] ?? 0);
$isEdit = $botId > 0;
$bot = null;

if ($isEdit) {
    $bot = $db->fetch("SELECT * FROM ai_bots WHERE id = ? AND user_id = ?", [$botId, $userId]);
    if (!$bot) {
        setFlash('danger', 'Bot not found.');
        redirect('dashboard/ai-chatbot.php');
    }
}

// Load WA accounts for dropdown
$waAccounts = $db->fetchAll('SELECT id, phone_number, business_name FROM whatsapp_accounts WHERE user_id = ?', [$userId]);

// Load enabled AI models from settings (fallback to all models if settings not seeded yet)
$enabledModels = [];
if ($settings->get('ai_openai_enabled', '1') !== '0') {
    $enabledModels[] = ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'desc' => 'OpenAI most capable model with vision', 'icon' => 'bi-stars'];
    $enabledModels[] = ['id' => 'gpt-4.1', 'name' => 'GPT-4.1', 'desc' => 'OpenAI latest model with improved reasoning', 'icon' => 'bi-lightning-charge'];
}
if ($settings->get('ai_gemini_enabled', '1') !== '0') {
    $enabledModels[] = ['id' => 'gemini', 'name' => 'Gemini Pro', 'desc' => 'Google Gemini with multimodal capabilities', 'icon' => 'bi-google'];
}
if ($settings->get('ai_claude_enabled', '0') === '1') {
    $enabledModels[] = ['id' => 'claude', 'name' => 'Claude', 'desc' => 'Anthropic Claude with long context window', 'icon' => 'bi-chat-square-heart'];
}
if ($settings->get('ai_custom_enabled', '1') !== '0') {
    $enabledModels[] = ['id' => 'custom', 'name' => 'Custom API', 'desc' => 'Use your own AI model endpoint', 'icon' => 'bi-gear'];
}
if (empty($enabledModels)) {
    // Ultimate fallback — show GPT-4o always
    $enabledModels[] = ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'desc' => 'OpenAI most capable model', 'icon' => 'bi-stars'];
}

// Load knowledge base items if editing
$documents = [];
$urls = [];
$qaPairs = [];
if ($isEdit) {
    try {
        $documents = $db->fetchAll("SELECT d.*, kb.id as kb_id FROM ai_kb_documents d JOIN ai_knowledge_bases kb ON d.kb_id = kb.id WHERE kb.bot_id = ? ORDER BY d.created_at DESC", [$botId]);
        $urls = $db->fetchAll("SELECT u.*, kb.id as kb_id FROM ai_kb_urls u JOIN ai_knowledge_bases kb ON u.kb_id = kb.id WHERE kb.bot_id = ? ORDER BY u.created_at DESC", [$botId]);
        $qaPairs = $db->fetchAll("SELECT q.*, kb.id as kb_id FROM ai_kb_qa_pairs q JOIN ai_knowledge_bases kb ON q.kb_id = kb.id WHERE kb.bot_id = ? ORDER BY q.created_at DESC", [$botId]);
    } catch (Exception $e) {
        $documents = $urls = $qaPairs = [];
    }
}

$pageTitle = $isEdit ? 'Edit AI Bot' : 'Create AI Bot';
$extraCss = [asset('assets/css/dashboard.css'), asset('assets/css/ai-chatbot.css')];
$extraJs = ['https://cdn.jsdelivr.net/npm/sweetalert2@11', asset('assets/js/ai-chatbot.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title"><?= $isEdit ? '<i class="bi bi-pencil-square"></i> Edit: ' . e($bot['name']) : '<i class="bi bi-plus-circle"></i> Create AI Bot'; ?></h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>">AI ChatBot Builder</a>
                    <i class="bi bi-chevron-right"></i>
                    <span><?= $isEdit ? 'Edit' : 'Create'; ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-pills mb-4 flex-wrap gap-2" id="botEditorTabs" role="tablist" style="overflow-x: auto; flex-wrap: nowrap !important; white-space: nowrap; -webkit-overflow-scrolling: touch;">
            <li class="nav-item"><a class="nav-link active btn-sm" id="tab-general" data-bs-toggle="pill" data-bs-target="#pane-general" href="#pane-general" style="border-radius: 8px;"><i class="bi bi-gear me-1"></i>General</a></li>
            <li class="nav-item"><a class="nav-link btn-sm" id="tab-knowledge" data-bs-toggle="pill" data-bs-target="#pane-knowledge" href="#pane-knowledge" style="border-radius: 8px;"><i class="bi bi-book me-1"></i>Knowledge Base</a></li>
            <li class="nav-item"><a class="nav-link btn-sm" id="tab-personality" data-bs-toggle="pill" data-bs-target="#pane-personality" href="#pane-personality" style="border-radius: 8px;"><i class="bi bi-stars me-1"></i>AI Personality</a></li>
            <li class="nav-item"><a class="nav-link btn-sm" id="tab-model" data-bs-toggle="pill" data-bs-target="#pane-model" href="#pane-model" style="border-radius: 8px;"><i class="bi bi-cpu me-1"></i>Model Selection</a></li>
            <li class="nav-item"><a class="nav-link btn-sm" id="tab-handover" data-bs-toggle="pill" data-bs-target="#pane-handover" href="#pane-handover" style="border-radius: 8px;"><i class="bi bi-person-check me-1"></i>Human Handover</a></li>
            <li class="nav-item"><a class="nav-link btn-sm" id="tab-test" data-bs-toggle="pill" data-bs-target="#pane-test" href="#pane-test" style="border-radius: 8px;"><i class="bi bi-chat-dots me-1"></i>Test Bot</a></li>
        </ul>

        <form id="botEditorForm">
            <input type="hidden" name="bot_id" value="<?= $botId; ?>">

            <div class="tab-content">
                <!-- Tab 1: General -->
                <div class="tab-pane fade show active" id="pane-general">
                    <div class="card" style="border-radius: var(--border-radius);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-gear text-primary me-2"></i>General Settings</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Bot Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= e($bot['name'] ?? ''); ?>" placeholder="e.g. Sales Assistant" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp Number</label>
                                    <select name="whatsapp_account_id" class="form-control">
                                        <option value="">— Select WhatsApp Account —</option>
                                        <?php foreach ($waAccounts as $wa): ?>
                                        <option value="<?= $wa['id']; ?>" <?= ($bot['whatsapp_account_id'] ?? '') == $wa['id'] ? 'selected' : ''; ?>>
                                            <?= e($wa['phone_number']); ?> <?= $wa['business_name'] ? '(' . e($wa['business_name']) . ')' : ''; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of what this bot does..."><?= e($bot['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="status" id="botStatus" <?= ($bot['status'] ?? 'inactive') === 'active' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="botStatus">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Knowledge Base -->
                <div class="tab-pane fade" id="pane-knowledge">
                    <div class="row g-4">
                        <!-- File Upload -->
                        <div class="col-lg-6">
                            <div class="card h-100" style="border-radius: var(--border-radius);">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Upload Documents</h6>
                                    <div id="dropZone" class="text-center p-4" style="border: 2px dashed var(--border-color); border-radius: var(--border-radius); cursor: pointer; transition: all 0.2s;">
                                        <i class="bi bi-cloud-arrow-up" style="font-size: 2.5rem; color: var(--primary);"></i>
                                        <p class="mb-1 mt-2 fw-semibold">Drag & drop files here</p>
                                        <p class="text-muted mb-0" style="font-size: 0.8125rem;">Upload PDF, DOCX, TXT, or CSV (Max 10MB)</p>
                                        <input type="file" id="fileUpload" accept=".pdf,.docx,.txt,.csv" class="d-none" multiple>
                                    </div>
                                    <div id="uploadedFiles" class="mt-3">
                                        <?php foreach ($documents as $doc): ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2" style="background: var(--bg-secondary); border-radius: 8px;" data-id="<?= $doc['id']; ?>">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-file-earmark-text text-primary"></i>
                                                <span style="font-size: 0.8125rem;"><?= e($doc['file_name'] ?? 'Document'); ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteKnowledge('document', <?= $doc['id']; ?>, this)" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- URL Training -->
                        <div class="col-lg-6">
                            <div class="card h-100" style="border-radius: var(--border-radius);">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-globe text-primary me-2"></i>URL Training</h6>
                                    <div class="input-group mb-3">
                                        <input type="url" id="crawlUrl" class="form-control" placeholder="https://example.com/page">
                                        <button type="button" class="btn btn-primary" id="btnCrawlUrl"><i class="bi bi-arrow-clockwise me-1"></i>Crawl</button>
                                    </div>
                                    <div id="urlsList">
                                        <?php foreach ($urls as $url): ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2" style="background: var(--bg-secondary); border-radius: 8px;" data-id="<?= $url['id']; ?>">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-link-45deg text-primary"></i>
                                                <span style="font-size: 0.8125rem;"><?= e($url['url']); ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteKnowledge('url', <?= $url['id']; ?>, this)" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Q&A Training -->
                        <div class="col-lg-6">
                            <div class="card h-100" style="border-radius: var(--border-radius);">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Q&A Training</h6>
                                    <div id="qaPairsContainer">
                                        <?php if (!empty($qaPairs)): ?>
                                        <?php foreach ($qaPairs as $i => $qa): ?>
                                        <div class="qa-pair mb-3 p-3" style="background: var(--bg-secondary); border-radius: 8px;">
                                            <div class="d-flex justify-content-between mb-2">
                                                <label class="form-label mb-0 fw-semibold" style="font-size: 0.8125rem;">Q&A Pair</label>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.qa-pair').remove()" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
                                            </div>
                                            <input type="text" name="qa_question[]" class="form-control form-control-sm mb-2" placeholder="Question" value="<?= e($qa['question'] ?? ''); ?>">
                                            <textarea name="qa_answer[]" class="form-control form-control-sm" rows="2" placeholder="Answer"><?= e($qa['answer'] ?? ''); ?></textarea>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddQA"><i class="bi bi-plus-lg me-1"></i>Add Q&A Pair</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Tab 3: AI Personality -->
                <div class="tab-pane fade" id="pane-personality">
                    <div class="card" style="border-radius: var(--border-radius);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-stars text-primary me-2"></i>AI Personality & Behavior</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Bot Role</label>
                                    <input type="text" name="bot_role" class="form-control" value="<?= e($bot['bot_role'] ?? ''); ?>" placeholder="e.g. Customer Support Agent">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business Type</label>
                                    <input type="text" name="business_type" class="form-control" value="<?= e($bot['business_type'] ?? ''); ?>" placeholder="e.g. E-commerce, SaaS, Healthcare">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Response Tone</label>
                                    <select name="response_tone" class="form-control">
                                        <?php
                                        $tones = ['professional' => 'Professional', 'friendly' => 'Friendly', 'sales' => 'Sales-Oriented', 'support' => 'Customer Support', 'healthcare' => 'Healthcare', 'real_estate' => 'Real Estate', 'custom' => 'Custom'];
                                        foreach ($tones as $val => $label):
                                        ?>
                                        <option value="<?= $val; ?>" <?= ($bot['response_tone'] ?? 'professional') === $val ? 'selected' : ''; ?>><?= $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Response Length</label>
                                    <select name="response_length" class="form-control">
                                        <option value="concise" <?= ($bot['response_length'] ?? 'moderate') === 'concise' ? 'selected' : ''; ?>>Concise</option>
                                        <option value="moderate" <?= ($bot['response_length'] ?? 'moderate') === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                        <option value="detailed" <?= ($bot['response_length'] ?? 'moderate') === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Language</label>
                                    <input type="text" name="language" class="form-control" value="<?= e($bot['language'] ?? 'English'); ?>" placeholder="English">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">System Prompt</label>
                                    <textarea name="system_prompt" class="form-control" rows="8" style="font-family: 'Courier New', monospace; font-size: 0.875rem;" placeholder="You are a helpful customer support assistant for [Company Name]. You help customers with product inquiries, order tracking, and general questions. Always be polite, concise, and accurate. If you don't know something, say so honestly."><?= e($bot['system_prompt'] ?? ''); ?></textarea>
                                    <small class="text-muted">Define how the AI should behave. This is the core instruction that shapes all responses.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Model Selection -->
                <div class="tab-pane fade" id="pane-model">
                    <div class="card" style="border-radius: var(--border-radius);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-cpu text-primary me-2"></i>AI Model Selection</h5>
                            <?php if (empty($enabledModels)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                                <p class="mt-2">No AI models are enabled. Contact administrator.</p>
                            </div>
                            <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($enabledModels as $model): ?>
                                <div class="col-md-6 col-lg-4">
                                    <label class="d-block">
                                        <input type="radio" name="model" value="<?= $model['id']; ?>" class="d-none model-radio" <?= ($bot['ai_model'] ?? 'gpt-4o') === $model['id'] ? 'checked' : ''; ?>>
                                        <div class="card h-100 model-card" style="border-radius: var(--border-radius); cursor: pointer; transition: all 0.2s; border: 2px solid var(--border-color);">
                                            <div class="card-body p-3 text-center">
                                                <div style="width: 50px; height: 50px; background: var(--primary-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                                                    <i class="bi <?= $model['icon']; ?>" style="font-size: 1.5rem; color: var(--primary);"></i>
                                                </div>
                                                <h6 class="fw-bold mb-1"><?= e($model['name']); ?></h6>
                                                <p class="text-muted mb-0" style="font-size: 0.75rem;"><?= e($model['desc']); ?></p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Custom API fields (shown when custom model is selected) -->
                            <div id="customApiFields" class="mt-4" style="display: <?= ($bot['ai_model'] ?? '') === 'custom' ? 'block' : 'none'; ?>;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">API Endpoint</label>
                                        <input type="url" name="custom_api_endpoint" class="form-control" value="<?= e($bot['custom_api_endpoint'] ?? ''); ?>" placeholder="https://api.example.com/v1/chat">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">API Key</label>
                                        <input type="password" name="custom_api_key" class="form-control" value="" placeholder="sk-...">
                                        <small class="text-muted"><?= $isEdit ? 'Leave blank to keep existing key' : ''; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Human Handover -->
                <div class="tab-pane fade" id="pane-handover">
                    <div class="card" style="border-radius: var(--border-radius);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-person-check text-primary me-2"></i>Human Handover Settings</h5>
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="handover_enabled" id="handoverEnabled" <?= ($bot['handover_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="handoverEnabled">Enable Human Handover</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">When enabled, the bot can transfer conversations to a human agent based on keywords or low confidence.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Trigger Keywords</label>
                                    <textarea name="handover_keywords" class="form-control" rows="3" placeholder="speak to agent, human, help, manager, complaint, escalate"><?= e($bot['handover_keywords'] ?? ''); ?></textarea>
                                    <small class="text-muted">Comma-separated keywords that trigger handover to human agent.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confidence Threshold: <strong id="thresholdValue"><?php
                                        $thresholdDisplay = isset($bot['handover_confidence_threshold']) ? round($bot['handover_confidence_threshold'] * 100) : 30;
                                        echo e($thresholdDisplay);
                                    ?>%</strong></label>
                                    <input type="range" name="handover_threshold" class="form-range" min="0" max="100" value="<?= e($thresholdDisplay); ?>" id="thresholdSlider">
                                    <div class="d-flex justify-content-between" style="font-size: 0.75rem; color: var(--text-muted);">
                                        <span>0% (Always handover)</span>
                                        <span>100% (Never handover)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 6: Test Bot -->
                <div class="tab-pane fade" id="pane-test">
                    <div class="card" style="border-radius: var(--border-radius);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-chat-dots text-primary me-2"></i>Test Your Bot</h5>
                            <?php if (!$isEdit): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                                <p class="mt-2">Save your bot first to enable testing.</p>
                            </div>
                            <?php else: ?>
                            <div id="chatPreview" style="height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; background: var(--bg-secondary); margin-bottom: 1rem;">
                                <div class="text-center text-muted py-5" id="chatEmpty">
                                    <i class="bi bi-chat-dots" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Send a message to test your bot</p>
                                </div>
                            </div>
                            <div class="input-group">
                                <input type="text" id="testMessage" class="form-control" placeholder="Type a message to test..." autocomplete="off">
                                <button type="button" class="btn btn-primary" id="btnSendTest"><i class="bi bi-send-fill"></i></button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="mt-4 d-flex justify-content-between">
                <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary px-4" id="btnSaveBot">
                    <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Update Bot' : 'Create Bot'; ?>
                </button>
            </div>
        </form>

    </main>
</div>

<script>
const botId = <?= $botId; ?>;
const csrfToken = '<?= CSRF::generateToken(); ?>';
const baseUrl = '<?= APP_URL; ?>/';
window.APP_BASE = '<?= baseUrl(); ?>';

// Model card selection
document.querySelectorAll('.model-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.model-card').forEach(c => c.style.borderColor = 'var(--border-color)');
        this.closest('label').querySelector('.model-card').style.borderColor = 'var(--primary)';
        document.getElementById('customApiFields').style.display = this.value === 'custom' ? 'block' : 'none';
    });
    if (radio.checked) radio.closest('label').querySelector('.model-card').style.borderColor = 'var(--primary)';
});

// Threshold slider
document.getElementById('thresholdSlider')?.addEventListener('input', function() {
    document.getElementById('thresholdValue').textContent = this.value + '%';
});

// Add Q&A Pair
document.getElementById('btnAddQA')?.addEventListener('click', function() {
    const html = `<div class="qa-pair mb-3 p-3" style="background: var(--bg-secondary); border-radius: 8px;">
        <div class="d-flex justify-content-between mb-2">
            <label class="form-label mb-0 fw-semibold" style="font-size: 0.8125rem;">Q&A Pair</label>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.qa-pair').remove()" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
        </div>
        <input type="text" name="qa_question[]" class="form-control form-control-sm mb-2" placeholder="Question">
        <textarea name="qa_answer[]" class="form-control form-control-sm" rows="2" placeholder="Answer"></textarea>
    </div>`;
    document.getElementById('qaPairsContainer').insertAdjacentHTML('beforeend', html);
});

// File Upload (drag & drop)
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileUpload');

dropZone?.addEventListener('click', () => fileInput.click());
dropZone?.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = 'var(--primary)'; dropZone.style.background = 'var(--primary-bg)'; });
dropZone?.addEventListener('dragleave', () => { dropZone.style.borderColor = 'var(--border-color)'; dropZone.style.background = 'transparent'; });
dropZone?.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--border-color)';
    dropZone.style.background = 'transparent';
    if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
});
fileInput?.addEventListener('change', () => { if (fileInput.files.length) uploadFiles(fileInput.files); });

function uploadFiles(files) {
    if (!botId) { Swal.fire('Info', 'Save the bot first before uploading documents.', 'info'); return; }
    Array.from(files).forEach(file => {
        if (file.size > 10 * 1024 * 1024) { Swal.fire('Error', `${file.name} exceeds 10MB limit.`, 'error'); return; }
        const fd = new FormData();
        fd.append('file', file);
        fd.append('bot_id', botId);
        fetch(baseUrl + 'api/ai-bot/upload-document.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    const html = `<div class="d-flex justify-content-between align-items-center p-2 mb-2" style="background: var(--bg-secondary); border-radius: 8px;" data-id="${data.id}">
                        <div class="d-flex align-items-center gap-2"><i class="bi bi-file-earmark-text text-primary"></i><span style="font-size: 0.8125rem;">${file.name}</span></div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteKnowledge('document', ${data.id}, this)" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
                    </div>`;
                    document.getElementById('uploadedFiles').insertAdjacentHTML('beforeend', html);
                } else { Swal.fire('Error', data.message || 'Upload failed.', 'error'); }
            }).catch(() => Swal.fire('Error', 'Network error.', 'error'));
    });
}

// URL Crawl
document.getElementById('btnCrawlUrl')?.addEventListener('click', function() {
    const url = document.getElementById('crawlUrl').value.trim();
    if (!url) return;
    if (!botId) { Swal.fire('Info', 'Save the bot first before adding URLs.', 'info'); return; }
    this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(baseUrl + 'api/ai-bot/add-url.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ bot_id: botId, url: url })
    }).then(r => r.json()).then(data => {
        this.disabled = false; this.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Crawl';
        if (data.success) {
            document.getElementById('crawlUrl').value = '';
            const html = `<div class="d-flex justify-content-between align-items-center p-2 mb-2" style="background: var(--bg-secondary); border-radius: 8px;" data-id="${data.id}">
                <div class="d-flex align-items-center gap-2"><i class="bi bi-link-45deg text-primary"></i><span style="font-size: 0.8125rem;">${url}</span></div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteKnowledge('url', ${data.id}, this)" style="padding: 2px 6px;"><i class="bi bi-x"></i></button>
            </div>`;
            document.getElementById('urlsList').insertAdjacentHTML('beforeend', html);
        } else { Swal.fire('Error', data.message || 'Failed to crawl URL.', 'error'); }
    }).catch(() => { this.disabled = false; this.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Crawl'; Swal.fire('Error', 'Network error.', 'error'); });
});

function deleteKnowledge(type, id, el) {
    fetch(baseUrl + 'api/ai-bot/delete-kb-item.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ type: type, item_id: id })
    }).then(r => r.json()).then(data => {
        if (data.success) el.closest('[data-id]').remove();
        else Swal.fire('Error', data.message || 'Failed to delete.', 'error');
    }).catch(() => Swal.fire('Error', 'Network error.', 'error'));
}

// Save Bot
document.getElementById('botEditorForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveBot');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const fd = new FormData(this);
    fd.set('status', document.getElementById('botStatus').checked ? 'active' : 'inactive');
    fd.set('handover_enabled', document.getElementById('handoverEnabled')?.checked ? '1' : '0');

    fetch(baseUrl + 'api/ai-bot/save.php', {
        method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd
    }).then(r => r.json()).then(data => {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Update Bot' : 'Create Bot'; ?>';
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', text: data.message || 'Bot saved successfully.', timer: 1500, showConfirmButton: false });
            if (!botId && data.bot_id) setTimeout(() => window.location.href = '<?= baseUrl('dashboard/ai-chatbot-editor.php'); ?>?id=' + data.bot_id, 1500);
        } else { Swal.fire('Error', data.message || 'Failed to save bot.', 'error'); }
    }).catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Update Bot' : 'Create Bot'; ?>'; Swal.fire('Error', 'Network error.', 'error'); });
});

// Test Bot
document.getElementById('btnSendTest')?.addEventListener('click', sendTestMessage);
document.getElementById('testMessage')?.addEventListener('keydown', function(e) { if (e.key === 'Enter') sendTestMessage(); });

function sendTestMessage() {
    const input = document.getElementById('testMessage');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    document.getElementById('chatEmpty')?.remove();

    const chatBox = document.getElementById('chatPreview');
    chatBox.insertAdjacentHTML('beforeend', `<div class="d-flex justify-content-end mb-3"><div style="background: var(--primary); color: #fff; padding: 8px 14px; border-radius: 14px 14px 4px 14px; max-width: 75%; font-size: 0.875rem;">${msg}</div></div>`);
    chatBox.insertAdjacentHTML('beforeend', `<div class="d-flex mb-3" id="typing"><div style="background: var(--border-color); padding: 8px 14px; border-radius: 14px 14px 14px 4px; font-size: 0.875rem;"><span class="spinner-border spinner-border-sm me-1"></span> Thinking...</div></div>`);
    chatBox.scrollTop = chatBox.scrollHeight;

    fetch(baseUrl + 'api/ai-bot/test-bot.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ bot_id: botId, message: msg })
    }).then(r => r.json()).then(data => {
        document.getElementById('typing')?.remove();
        const reply = data.success ? data.reply : (data.message || 'Error processing message.');
        chatBox.insertAdjacentHTML('beforeend', `<div class="d-flex mb-3"><div style="background: var(--border-color); padding: 8px 14px; border-radius: 14px 14px 14px 4px; max-width: 75%; font-size: 0.875rem;">${reply}</div></div>`);
        chatBox.scrollTop = chatBox.scrollHeight;
    }).catch(() => {
        document.getElementById('typing')?.remove();
        chatBox.insertAdjacentHTML('beforeend', `<div class="d-flex mb-3"><div style="background: #fce4ec; padding: 8px 14px; border-radius: 14px 14px 14px 4px; font-size: 0.875rem; color: #c62828;">Network error.</div></div>`);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
