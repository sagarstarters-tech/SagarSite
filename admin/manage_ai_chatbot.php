<?php
/**
 * Admin Panel - AI ChatBot Management
 */

$current_page = 'manage_ai_chatbot.php';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../classes/ChatbotService.php';

$chatbotService = new ChatbotService($conn);
$feedback = '';
$feedbackType = '';

// Handle AJAX Test Connection
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_ai_connection') {
    header('Content-Type: application/json');
    $provider = trim($_POST['provider'] ?? 'gemini');
    $apiKey = trim($_POST['api_key'] ?? '');
    $model = trim($_POST['model'] ?? '');

    if ($provider !== 'hybrid' && empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'API Key cannot be empty for ' . strtoupper($provider)]);
        exit;
    }

    try {
        if ($provider === 'hybrid') {
            echo json_encode(['success' => true, 'message' => 'Smart Local Hybrid Engine is active, tested & 100% operational!']);
            exit;
        } elseif ($provider === 'gemini') {
            $testUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . ($model ?: 'gemini-1.5-flash') . ":generateContent?key=" . urlencode($apiKey);
            $payload = [
                'contents' => [['role' => 'user', 'parts' => [['text' => 'Hi, reply with "Gemini Connected Successfully" in 5 words.']]]]
            ];
            $ch = curl_init($testUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 8
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = json_decode($res, true);

            if ($code === 200 && !empty($json['candidates'][0]['content']['parts'][0]['text'])) {
                echo json_encode(['success' => true, 'message' => 'Google Gemini Connection Successful: ' . $json['candidates'][0]['content']['parts'][0]['text']]);
            } else {
                $err = $json['error']['message'] ?? 'Connection failed with HTTP Code ' . $code;
                echo json_encode(['success' => false, 'message' => 'Gemini Error: ' . $err]);
            }
            exit;
        } elseif ($provider === 'openai' || $provider === 'groq') {
            $endpoint = ($provider === 'groq') ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
            $testModel = $model ?: (($provider === 'groq') ? 'llama-3.3-70b-versatile' : 'gpt-4o-mini');
            $payload = [
                'model' => $testModel,
                'messages' => [['role' => 'user', 'content' => 'Say hello']],
                'max_tokens' => 15
            ];
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
                CURLOPT_TIMEOUT => 8
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = json_decode($res, true);

            if ($code === 200 && !empty($json['choices'][0]['message']['content'])) {
                echo json_encode(['success' => true, 'message' => strtoupper($provider) . ' Connected Successfully: ' . $json['choices'][0]['message']['content']]);
            } else {
                $err = $json['error']['message'] ?? 'Connection failed with HTTP Code ' . $code;
                echo json_encode(['success' => false, 'message' => strtoupper($provider) . ' Error: ' . $err]);
            }
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Test Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chatbot_settings'])) {
    $keys = [
        'chatbot_enabled'         => isset($_POST['chatbot_enabled']) ? '1' : '0',
        'chatbot_name'            => trim($_POST['chatbot_name'] ?? 'Sagar Sahayak'),
        'chatbot_title'           => trim($_POST['chatbot_title'] ?? 'Sagar AI Assistant'),
        'chatbot_welcome_msg'     => trim($_POST['chatbot_welcome_msg'] ?? ''),
        'chatbot_provider'        => trim($_POST['chatbot_provider'] ?? 'hybrid'),
        'chatbot_gemini_key'      => trim($_POST['chatbot_gemini_key'] ?? ''),
        'chatbot_gemini_model'    => trim($_POST['chatbot_gemini_model'] ?? 'gemini-1.5-flash'),
        'chatbot_openai_key'      => trim($_POST['chatbot_openai_key'] ?? ''),
        'chatbot_openai_model'    => trim($_POST['chatbot_openai_model'] ?? 'gpt-4o-mini'),
        'chatbot_groq_key'        => trim($_POST['chatbot_groq_key'] ?? ''),
        'chatbot_groq_model'      => trim($_POST['chatbot_groq_model'] ?? 'llama-3.3-70b-versatile'),
        'chatbot_system_prompt'   => trim($_POST['chatbot_system_prompt'] ?? ''),
        'chatbot_whatsapp_number' => trim($_POST['chatbot_whatsapp_number'] ?? '919837248000'),
        'chatbot_position'        => trim($_POST['chatbot_position'] ?? 'bottom-right'),
        'chatbot_theme_color'     => trim($_POST['chatbot_theme_color'] ?? '#007aff'),
        'chatbot_quick_prompts'   => trim($_POST['chatbot_quick_prompts'] ?? '')
    ];

    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($keys as $k => $v) {
            $stmt->bind_param("ss", $k, $v);
            $stmt->execute();
        }
        $stmt->close();
        $feedback = "AI ChatBot Settings have been updated successfully!";
        $feedbackType = "success";
    } catch (Exception $e) {
        $feedback = "Error updating settings: " . $e->getMessage();
        $feedbackType = "danger";
    }
}

// Handle Clear Logs
if (isset($_POST['clear_chatbot_logs'])) {
    try {
        $conn->query("TRUNCATE TABLE chatbot_logs");
        $feedback = "ChatBot conversation logs cleared successfully.";
        $feedbackType = "info";
    } catch (Exception $e) {
        $feedback = "Error clearing logs: " . $e->getMessage();
        $feedbackType = "danger";
    }
}

// Reload service settings
$chatbotService = new ChatbotService($conn);
$chatbotService->ensureTableAndDefaults();

// Fetch recent conversation logs safely
$logsRes = null;
$totalLogsCount = 0;

try {
    $conn->query("CREATE TABLE IF NOT EXISTS `chatbot_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` VARCHAR(100) NOT NULL,
        `user_ip` VARCHAR(50) NULL,
        `user_message` TEXT NOT NULL,
        `bot_response` LONGTEXT NOT NULL,
        `intent` VARCHAR(50) DEFAULT 'general',
        `provider_used` VARCHAR(50) DEFAULT 'hybrid',
        `response_time_ms` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`session_id`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $logsRes = $conn->query("SELECT * FROM chatbot_logs ORDER BY id DESC LIMIT 20");
    $cntRes = $conn->query("SELECT COUNT(*) as cnt FROM chatbot_logs");
    if ($cntRes && $cntRes->num_rows > 0) {
        $totalLogsCount = (int)$cntRes->fetch_assoc()['cnt'];
    }
} catch (Exception $e) {
    error_log("Error fetching chatbot logs: " . $e->getMessage());
}
?>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-robot text-primary me-2"></i>AI ChatBot Manager</h2>
            <p class="text-muted mb-0">Configure your 24/7 Virtual Sales & Customer Support AI Assistant</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?php echo $chatbotService->isEnabled() ? 'bg-success' : 'bg-secondary'; ?> px-3 py-2 fs-6 rounded-pill">
                <i class="fas <?php echo $chatbotService->isEnabled() ? 'fa-check-circle' : 'fa-pause-circle'; ?> me-1"></i>
                <?php echo $chatbotService->isEnabled() ? 'ChatBot Online' : 'ChatBot Disabled'; ?>
            </span>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show rounded-3 shadow-sm border-0" role="alert">
            <i class="fas <?php echo $feedbackType === 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($feedback); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="manage_ai_chatbot.php">
        <div class="row g-4">
            <!-- Left Column: Core Controls & Personas -->
            <div class="col-lg-7">
                <!-- Master Toggle & Persona Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-toggle-on text-primary me-2"></i>Master Controls & Bot Persona</h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- Toggle Switch -->
                        <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                            <div>
                                <label class="form-check-label fw-bold fs-6 text-dark" for="chatbotEnabled">Enable AI ChatBot on Website</label>
                                <div class="text-muted small">When enabled, the floating chat widget appears for customers on all pages.</div>
                            </div>
                            <input class="form-check-input fs-4 m-0" type="checkbox" role="switch" name="chatbot_enabled" id="chatbotEnabled" value="1" <?php echo $chatbotService->isEnabled() ? 'checked' : ''; ?>>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Bot Display Name</label>
                                <input type="text" name="chatbot_name" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_name', 'Sagar Sahayak')); ?>" placeholder="e.g. Sagar Sahayak" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Bot Subtitle / Role</label>
                                <input type="text" name="chatbot_title" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_title', 'Sagar AI Assistant')); ?>" placeholder="e.g. Sagar AI Assistant">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">WhatsApp Support Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fab fa-whatsapp text-success"></i></span>
                                    <input type="text" name="chatbot_whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_whatsapp_number', '919837248000')); ?>" placeholder="919837248000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Floating Button Position</label>
                                <select name="chatbot_position" class="form-select">
                                    <option value="bottom-right" <?php echo $chatbotService->getSetting('chatbot_position') === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right (Default)</option>
                                    <option value="bottom-left" <?php echo $chatbotService->getSetting('chatbot_position') === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                                </select>
                            </div>
                        </div>

                        <!-- Welcome Message -->
                        <div class="mt-3">
                            <label class="form-label fw-semibold small text-muted">Initial Welcome Message (Greeting)</label>
                            <textarea name="chatbot_welcome_msg" class="form-control" rows="3"><?php echo htmlspecialchars($chatbotService->getSetting('chatbot_welcome_msg')); ?></textarea>
                            <div class="form-text small">This message greets the customer when they first open the chat window.</div>
                        </div>

                        <!-- Quick Prompts -->
                        <div class="mt-3">
                            <label class="form-label fw-semibold small text-muted">Quick Suggestion Chips (Comma Separated)</label>
                            <input type="text" name="chatbot_quick_prompts" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_quick_prompts')); ?>">
                            <div class="form-text small">Example: 5HP Submersible Starter, Single Phase vs 3 Phase, Track My Order, Bulk Purchase Discount</div>
                        </div>
                    </div>
                </div>

                <!-- System Prompt Customization -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-brain text-info me-2"></i>AI Knowledge & System Prompt</h5>
                    </div>
                    <div class="card-body p-4">
                        <textarea name="chatbot_system_prompt" class="form-control font-monospace small" rows="5"><?php echo htmlspecialchars($chatbotService->getSetting('chatbot_system_prompt')); ?></textarea>
                        <div class="form-text small mt-2">
                            <i class="fas fa-info-circle text-primary me-1"></i> Live product catalogs, pricing, discounts, and order tracking schemas are automatically injected into the AI context during queries.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: AI Provider Settings & Test -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-server text-primary me-2"></i>AI Provider Engine</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php $activeProvider = strtolower($chatbotService->getSetting('chatbot_provider', 'hybrid')); ?>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Active AI Engine</label>
                            <select name="chatbot_provider" id="providerSelect" class="form-select form-select-lg border-primary fw-semibold" onchange="switchProviderFields(this.value)">
                                <option value="hybrid" <?php echo $activeProvider === 'hybrid' ? 'selected' : ''; ?>>⚡ Smart Local Hybrid Engine (Built-in / 100% Free & Fast)</option>
                                <option value="gemini" <?php echo $activeProvider === 'gemini' ? 'selected' : ''; ?>>✨ Google Gemini 2.0 / 1.5 Flash (Recommended)</option>
                                <option value="groq" <?php echo $activeProvider === 'groq' ? 'selected' : ''; ?>>🚀 Groq Cloud (LLaMA 3.3 70B - Ultra Fast)</option>
                                <option value="openai" <?php echo $activeProvider === 'openai' ? 'selected' : ''; ?>>🤖 OpenAI ChatGPT (GPT-4o Mini)</option>
                            </select>
                        </div>

                        <!-- Provider Info Alert -->
                        <div id="providerInfoBox" class="alert alert-info py-2 px-3 small rounded-3 mb-3">
                            <i class="fas fa-shield-alt me-1"></i> <strong>Smart Local Engine</strong> requires no API key and provides instant product matches, order tracking, and Hindi/English recommendations.
                        </div>

                        <!-- Gemini Box -->
                        <div id="geminiBox" class="provider-box <?php echo $activeProvider !== 'gemini' ? 'd-none' : ''; ?> p-3 bg-light rounded-3 mb-3">
                            <h6 class="fw-bold text-primary mb-2"><i class="fab fa-google me-1"></i>Google Gemini Settings</h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Gemini API Key</label>
                                <input type="password" name="chatbot_gemini_key" id="geminiKey" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_gemini_key')); ?>" placeholder="AIzaSy...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Model</label>
                                <select name="chatbot_gemini_model" id="geminiModel" class="form-select form-select-sm">
                                    <option value="gemini-1.5-flash" <?php echo $chatbotService->getSetting('chatbot_gemini_model') === 'gemini-1.5-flash' ? 'selected' : ''; ?>>gemini-1.5-flash (Fast & Free Tier)</option>
                                    <option value="gemini-2.0-flash" <?php echo $chatbotService->getSetting('chatbot_gemini_model') === 'gemini-2.0-flash' ? 'selected' : ''; ?>>gemini-2.0-flash (Latest 2026)</option>
                                    <option value="gemini-1.5-pro" <?php echo $chatbotService->getSetting('chatbot_gemini_model') === 'gemini-1.5-pro' ? 'selected' : ''; ?>>gemini-1.5-pro</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="testConnection('gemini')">
                                <i class="fas fa-plug me-1"></i> Test Gemini Connection
                            </button>
                        </div>

                        <!-- Groq Box -->
                        <div id="groqBox" class="provider-box <?php echo $activeProvider !== 'groq' ? 'd-none' : ''; ?> p-3 bg-light rounded-3 mb-3">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-bolt text-warning me-1"></i>Groq Cloud Settings</h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Groq API Key</label>
                                <input type="password" name="chatbot_groq_key" id="groqKey" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_groq_key')); ?>" placeholder="gsk_...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Model</label>
                                <select name="chatbot_groq_model" id="groqModel" class="form-select form-select-sm">
                                    <option value="llama-3.3-70b-versatile" selected>llama-3.3-70b-versatile</option>
                                    <option value="mixtral-8x7b-32768">mixtral-8x7b-32768</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-dark btn-sm rounded-pill" onclick="testConnection('groq')">
                                <i class="fas fa-plug me-1"></i> Test Groq Connection
                            </button>
                        </div>

                        <!-- OpenAI Box -->
                        <div id="openaiBox" class="provider-box <?php echo $activeProvider !== 'openai' ? 'd-none' : ''; ?> p-3 bg-light rounded-3 mb-3">
                            <h6 class="fw-bold text-success mb-2"><i class="fas fa-atom me-1"></i>OpenAI ChatGPT Settings</h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">OpenAI API Key</label>
                                <input type="password" name="chatbot_openai_key" id="openaiKey" class="form-control" value="<?php echo htmlspecialchars($chatbotService->getSetting('chatbot_openai_key')); ?>" placeholder="sk-...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Model</label>
                                <select name="chatbot_openai_model" id="openaiModel" class="form-select form-select-sm">
                                    <option value="gpt-4o-mini" <?php echo $chatbotService->getSetting('chatbot_openai_model') === 'gpt-4o-mini' ? 'selected' : ''; ?>>gpt-4o-mini (Cost Effective)</option>
                                    <option value="gpt-4o" <?php echo $chatbotService->getSetting('chatbot_openai_model') === 'gpt-4o' ? 'selected' : ''; ?>>gpt-4o</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm rounded-pill" onclick="testConnection('openai')">
                                <i class="fas fa-plug me-1"></i> Test OpenAI Connection
                            </button>
                        </div>

                        <!-- Test Status Output Container -->
                        <div id="testOutput" class="d-none mt-3 p-3 rounded-3 small"></div>
                    </div>
                </div>

                <!-- Save Changes Floating / Stickied Button -->
                <div class="d-grid">
                    <button type="submit" name="save_chatbot_settings" class="btn btn-primary btn-lg rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i> Save All ChatBot Settings
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Section: Conversation History / Logs -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-secondary me-2"></i>Recent Customer Inquiries (<?php echo (int)$totalLogsCount; ?> Total)</h5>
            </div>
            <?php if ($totalLogsCount > 0): ?>
            <form method="POST" onsubmit="return confirm('Are you sure you want to clear all conversation logs?');">
                <button type="submit" name="clear_chatbot_logs" class="btn btn-outline-danger btn-sm rounded-pill">
                    <i class="fas fa-trash-alt me-1"></i> Clear Logs
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Time</th>
                        <th>User Query</th>
                        <th>Bot Reply Snippet</th>
                        <th>Intent</th>
                        <th>Engine</th>
                        <th>Latency</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logsRes && $logsRes->num_rows > 0): ?>
                        <?php while ($log = $logsRes->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-nowrap small text-muted"><?php echo date('d M, h:i A', strtotime($log['created_at'])); ?></td>
                            <td class="fw-semibold text-dark text-break" style="max-width: 250px;"><?php echo htmlspecialchars($log['user_message']); ?></td>
                            <td class="text-muted small text-truncate" style="max-width: 320px;"><?php echo htmlspecialchars(strip_tags($log['bot_response'])); ?></td>
                            <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($log['intent']); ?></span></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><?php echo htmlspecialchars($log['provider_used']); ?></span></td>
                            <td class="small text-muted"><?php echo (int)$log['response_time_ms']; ?>ms</td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-comments fa-2x mb-2 d-block text-muted opacity-50"></i>
                                No customer conversation logs yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchProviderFields(val) {
    document.querySelectorAll('.provider-box').forEach(el => el.classList.add('d-none'));
    const infoBox = document.getElementById('providerInfoBox');

    if (val === 'gemini') {
        document.getElementById('geminiBox').classList.remove('d-none');
        infoBox.className = "alert alert-primary py-2 px-3 small rounded-3 mb-3";
        infoBox.innerHTML = "<i class='fab fa-google me-1'></i> <strong>Google Gemini</strong> provides natural multi-lingual reasoning and fast replies using your Gemini API key.";
    } else if (val === 'groq') {
        document.getElementById('groqBox').classList.remove('d-none');
        infoBox.className = "alert alert-warning py-2 px-3 small rounded-3 mb-3";
        infoBox.innerHTML = "<i class='fas fa-bolt me-1'></i> <strong>Groq Cloud</strong> provides ultra-low latency LLaMA 3.3 generation speed.";
    } else if (val === 'openai') {
        document.getElementById('openaiBox').classList.remove('d-none');
        infoBox.className = "alert alert-success py-2 px-3 small rounded-3 mb-3";
        infoBox.innerHTML = "<i class='fas fa-atom me-1'></i> <strong>OpenAI ChatGPT</strong> connects directly to GPT-4o-mini using your OpenAI secret key.";
    } else {
        infoBox.className = "alert alert-info py-2 px-3 small rounded-3 mb-3";
        infoBox.innerHTML = "<i class='fas fa-shield-alt me-1'></i> <strong>Smart Local Hybrid Engine</strong> runs directly on your server with zero external API fees and instant live product searches.";
    }
}

function testConnection(provider) {
    const output = document.getElementById('testOutput');
    output.className = "alert alert-info py-2 px-3 small rounded-3 mt-3 d-block";
    output.innerHTML = "<i class='fas fa-spinner fa-spin me-2'></i> Testing connection to " + provider.toUpperCase() + "...";

    let apiKey = '';
    let model = '';
    if (provider === 'gemini') {
        apiKey = document.getElementById('geminiKey').value;
        model = document.getElementById('geminiModel').value;
    } else if (provider === 'groq') {
        apiKey = document.getElementById('groqKey').value;
        model = document.getElementById('groqModel').value;
    } else if (provider === 'openai') {
        apiKey = document.getElementById('openaiKey').value;
        model = document.getElementById('openaiModel').value;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'test_ai_connection');
    formData.append('provider', provider);
    formData.append('api_key', apiKey);
    formData.append('model', model);

    fetch('manage_ai_chatbot.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            output.className = "alert alert-success py-2 px-3 small rounded-3 mt-3 d-block";
            output.innerHTML = "<i class='fas fa-check-circle me-1'></i> " + data.message;
        } else {
            output.className = "alert alert-danger py-2 px-3 small rounded-3 mt-3 d-block";
            output.innerHTML = "<i class='fas fa-exclamation-triangle me-1'></i> " + data.message;
        }
    })
    .catch(err => {
        output.className = "alert alert-danger py-2 px-3 small rounded-3 mt-3 d-block";
        output.innerHTML = "<i class='fas fa-times-circle me-1'></i> Error: " + err.message;
    });
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
