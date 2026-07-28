<?php
/**
 * WAPI SaaS - Admin AI Settings
 * Configure AI provider API keys, models, and defaults
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();
$hideNav = true;

// Handle POST - Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $aiSettings = [
        'ai_openai_api_key' => sanitize($_POST['ai_openai_api_key'] ?? ''),
        'ai_openai_enabled' => isset($_POST['ai_openai_enabled']) ? '1' : '0',
        'ai_gemini_api_key' => sanitize($_POST['ai_gemini_api_key'] ?? ''),
        'ai_gemini_enabled' => isset($_POST['ai_gemini_enabled']) ? '1' : '0',
        'ai_claude_api_key' => sanitize($_POST['ai_claude_api_key'] ?? ''),
        'ai_claude_enabled' => isset($_POST['ai_claude_enabled']) ? '1' : '0',
        'ai_custom_enabled' => isset($_POST['ai_custom_enabled']) ? '1' : '0',
        'ai_default_model' => sanitize($_POST['ai_default_model'] ?? 'gpt-4o'),
        'ai_max_tokens_per_response' => sanitizeInt($_POST['ai_max_tokens_per_response'] ?? 1024),
        'ai_max_context_messages' => sanitizeInt($_POST['ai_max_context_messages'] ?? 10),
        'ai_rate_limit_default' => sanitizeInt($_POST['ai_rate_limit_default'] ?? 100),
        'ai_default_system_prompt' => sanitize($_POST['ai_default_system_prompt'] ?? ''),
    ];

    foreach ($aiSettings as $key => $value) {
        // Don't overwrite API keys with empty values (preserve existing)
        if (in_array($key, ['ai_openai_api_key', 'ai_gemini_api_key', 'ai_claude_api_key']) && empty($value)) {
            continue;
        }
        $exists = $db->exists('settings', 'setting_key = ?', [$key]);
        if ($exists) {
            $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => 'ai',
                'setting_type' => 'text'
            ]);
        }
    }

    setFlash('success', 'AI settings saved successfully!');
    redirect('admin/ai-settings.php');
}

// Load current settings
$aiOpenaiKey = $settings->get('ai_openai_api_key', '');
$aiOpenaiEnabled = $settings->get('ai_openai_enabled', '1');
$aiGeminiKey = $settings->get('ai_gemini_api_key', '');
$aiGeminiEnabled = $settings->get('ai_gemini_enabled', '1');
$aiClaudeKey = $settings->get('ai_claude_api_key', '');
$aiClaudeEnabled = $settings->get('ai_claude_enabled', '0');
$aiCustomEnabled = $settings->get('ai_custom_enabled', '1');
$aiDefaultModel = $settings->get('ai_default_model', 'gpt-4o');
$aiMaxTokens = $settings->get('ai_max_tokens_per_response', '1024');
$aiMaxContext = $settings->get('ai_max_context_messages', '10');
$aiRateLimit = $settings->get('ai_rate_limit_default', '100');
$aiDefaultPrompt = $settings->get('ai_default_system_prompt', 'You are a helpful customer support assistant. Answer questions based on the provided knowledge base. If you cannot find the answer, politely let the customer know and offer to connect them with a human agent.');

// Stats
try { $totalBots = $db->count('ai_bots', '1'); } catch (Exception $e) { $totalBots = 0; }
try { $activeBots = $db->count('ai_bots', "status = 'active'"); } catch (Exception $e) { $activeBots = 0; }
try { $totalConversations = $db->count('ai_conversations', '1'); } catch (Exception $e) { $totalConversations = 0; }

$pageTitle = 'AI Settings';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">🤖 AI Settings</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('admin/'); ?>">Admin</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>AI Settings</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-check-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="bi bi-robot"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalBots; ?></div>
                        <div class="stat-label">Total AI Bots</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $activeBots; ?></div>
                        <div class="stat-label">Active Bots</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon info"><i class="bi bi-chat-dots-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= formatNumber($totalConversations); ?></div>
                        <div class="stat-label">Total Conversations</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST">
            <?= CSRF::tokenField(); ?>

            <!-- OpenAI -->
            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-cpu text-success me-2"></i>OpenAI (GPT-4o / GPT-4.1)</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ai_openai_enabled" id="openaiEnabled" <?= $aiOpenaiEnabled === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="openaiEnabled">Enabled</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input type="password" name="ai_openai_api_key" class="form-control" value="<?= e($aiOpenaiKey); ?>" placeholder="sk-..." id="openaiKey">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('openaiKey')"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gemini -->
            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-stars text-primary me-2"></i>Google Gemini</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ai_gemini_enabled" id="geminiEnabled" <?= $aiGeminiEnabled === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="geminiEnabled">Enabled</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input type="password" name="ai_gemini_api_key" class="form-control" value="<?= e($aiGeminiKey); ?>" placeholder="AIza..." id="geminiKey">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('geminiKey')"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text">Get your API key from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Claude -->
            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-lightning-fill me-2" style="color: #cc7832;"></i>Anthropic Claude</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ai_claude_enabled" id="claudeEnabled" <?= $aiClaudeEnabled === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="claudeEnabled">Enabled</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input type="password" name="ai_claude_api_key" class="form-control" value="<?= e($aiClaudeKey); ?>" placeholder="sk-ant-..." id="claudeKey">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('claudeKey')"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text">Get your API key from <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom API -->
            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-plug-fill text-info me-2"></i>Custom OpenAI-Compatible API</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ai_custom_enabled" id="customEnabled" <?= $aiCustomEnabled === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="customEnabled">Enabled</label>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size: 0.875rem;">Allow users to connect their own OpenAI-compatible API endpoints (e.g., local LLMs, Azure OpenAI, etc.)</p>
                </div>
            </div>

            <!-- Defaults -->
            <div class="card mb-4" style="border-radius: var(--border-radius);">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-gear-fill text-secondary me-2"></i>Default Configuration</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Default Model</label>
                            <select name="ai_default_model" class="form-select">
                                <option value="gpt-4o" <?= $aiDefaultModel === 'gpt-4o' ? 'selected' : ''; ?>>GPT-4o</option>
                                <option value="gpt-4.1" <?= $aiDefaultModel === 'gpt-4.1' ? 'selected' : ''; ?>>GPT-4.1</option>
                                <option value="gemini" <?= $aiDefaultModel === 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                                <option value="claude" <?= $aiDefaultModel === 'claude' ? 'selected' : ''; ?>>Claude</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Tokens/Response</label>
                            <input type="number" name="ai_max_tokens_per_response" class="form-control" value="<?= e($aiMaxTokens); ?>" min="100" max="4096">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Context Messages</label>
                            <input type="number" name="ai_max_context_messages" class="form-control" value="<?= e($aiMaxContext); ?>" min="1" max="50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rate Limit (msg/min)</label>
                            <input type="number" name="ai_rate_limit_default" class="form-control" value="<?= e($aiRateLimit); ?>" min="1" max="10000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Default System Prompt</label>
                            <textarea name="ai_default_system_prompt" class="form-control" rows="4" style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.8125rem;"><?= e($aiDefaultPrompt); ?></textarea>
                            <div class="form-text">This prompt is used when users don't provide their own system prompt.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="<?= baseUrl('admin/'); ?>" class="btn btn-outline-primary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
            </div>
        </form>
    </main>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
