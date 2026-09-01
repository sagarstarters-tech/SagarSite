<?php
require_once 'admin_header.php';

// Ensure chat widget columns exist (safe to run every time)
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_enabled TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_number VARCHAR(20) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_message VARCHAR(255) NOT NULL DEFAULT 'Hello, I have a question about your products.'");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS phone_number_id VARCHAR(50) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS meta_template_name VARCHAR(100) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS meta_template_lang VARCHAR(10) NOT NULL DEFAULT 'en'");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS waba_id VARCHAR(50) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS wa_header_image_url VARCHAR(500) NOT NULL DEFAULT ''");
// Admin notification columns (safe migration)
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_whatsapp_number VARCHAR(20) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_notify_on_new_order TINYINT(1) NOT NULL DEFAULT 1");

// Fetch current settings
$settings_query = "SELECT * FROM whatsapp_settings WHERE id = 1";
$result = $conn->query($settings_query);
$settings = $result->fetch_assoc();

if (!$settings) {
    // Failsafe insert if missing
    $conn->query("INSERT IGNORE INTO whatsapp_settings (id, message_template) VALUES (1, 'Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\n{CustomerName} Thank you for shopping with us.')");
    $settings = ['is_enabled'=>1, 'sender_number'=>'', 'api_token'=>'', 'sending_mode'=>'web', 'message_template'=>'Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\n{CustomerName} Thank you for shopping with us.', 'chat_widget_enabled'=>1, 'chat_widget_number'=>'', 'chat_widget_message'=>'Hello, I have a question about your products.'];
}

$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $sender_number = $conn->real_escape_string($_POST['sender_number']);
    $api_token = $conn->real_escape_string($_POST['api_token']);
    $phone_number_id = $conn->real_escape_string($_POST['phone_number_id'] ?? '');
    $sending_mode = $conn->real_escape_string($_POST['sending_mode']);
    $message_template = $conn->real_escape_string($_POST['message_template']);
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;

    // Chat Widget fields
    $chat_widget_enabled = isset($_POST['chat_widget_enabled']) ? 1 : 0;
    $chat_widget_number  = $conn->real_escape_string($_POST['chat_widget_number'] ?? '');
    $chat_widget_message = $conn->real_escape_string($_POST['chat_widget_message'] ?? 'Hello, I have a question about your products.');
    $meta_template_name = $conn->real_escape_string($_POST['meta_template_name'] ?? '');
    $meta_template_lang = $conn->real_escape_string($_POST['meta_template_lang'] ?? 'en');
    $waba_id = $conn->real_escape_string($_POST['waba_id'] ?? '');
    $wa_header_image_url = $conn->real_escape_string(trim($_POST['wa_header_image_url'] ?? ''));
    // Admin notification fields
    $admin_whatsapp_number = $conn->real_escape_string(trim($_POST['admin_whatsapp_number'] ?? ''));
    $admin_notify_on_new_order = isset($_POST['admin_notify_on_new_order']) ? 1 : 0;

    $update_query = "UPDATE whatsapp_settings SET 
        is_enabled = $is_enabled,
        sender_number = '$sender_number',
        api_token = '$api_token',
        sending_mode = '$sending_mode',
        message_template = '$message_template',
        phone_number_id = '$phone_number_id',
        chat_widget_enabled = $chat_widget_enabled,
        chat_widget_number = '$chat_widget_number',
        chat_widget_message = '$chat_widget_message',
        meta_template_name = '$meta_template_name',
        meta_template_lang = '$meta_template_lang',
        waba_id = '$waba_id',
        wa_header_image_url = '$wa_header_image_url',
        admin_whatsapp_number = '$admin_whatsapp_number',
        admin_notify_on_new_order = $admin_notify_on_new_order
        WHERE id = 1";

    if ($conn->query($update_query)) {
        $success_msg = "WhatsApp Settings updated successfully.";
        // Refresh settings
        $result = $conn->query($settings_query);
        $settings = $result->fetch_assoc();
    }
}

// Fetch logs
$logs_query = "SELECT wl.*, o.id as order_number, u.name as customer_name FROM whatsapp_logs wl JOIN orders o ON wl.order_id = o.id JOIN users u ON o.user_id = u.id ORDER BY wl.sent_at DESC LIMIT 50";
$logs = $conn->query($logs_query);
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="mb-0 text-dark fw-bold"><i class="fab fa-whatsapp me-2 text-success"></i> WhatsApp Notifications</h2>
        <p class="text-muted mb-0">Manage automated order status updates via WhatsApp.</p>
    </div>
</div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
    <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h5 class="fw-bold m-0">General Configuration</h5>
                        <div class="form-check form-switch fs-5 m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" id="enableWhatsapp" <?php echo ($settings['is_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="enableWhatsapp">Enable Feature</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Sending Mode</label>
                        <select name="sending_mode" class="form-select bg-light">
                            <option value="web" <?php echo ($settings['sending_mode'] === 'web') ? 'selected' : ''; ?>>WhatsApp Web (Manual Redirect)</option>
                            <option value="api" <?php echo ($settings['sending_mode'] === 'api') ? 'selected' : ''; ?>>WhatsApp Business API (Automated)</option>
                        </select>
                        <small class="text-muted">Web mode opens WhatsApp Web/Desktop safely. API mode requires official Meta credentials.</small>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4 text-start">
                            <label class="form-label fw-bold d-block">Sender Number</label>
                            <?php echo render_phone_input('sender_number', $settings['sender_number'], true); ?>
                        </div>
                        <div class="col-md-4 text-start">
                            <label class="form-label fw-bold d-block">Phone Number ID (Meta Graph)</label>
                            <input type="text" name="phone_number_id" class="form-control bg-light" placeholder="E.g. 1045612345678" value="<?php echo htmlspecialchars($settings['phone_number_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Business API Token <small class="text-muted">(Optional)</small></label>
                            <input type="password" name="api_token" class="form-control bg-light" placeholder="EAAI..." value="<?php echo htmlspecialchars($settings['api_token']); ?>">
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwWhatsapp">
                                <label class="form-check-label small text-muted" for="showPwWhatsapp">Show</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold">Meta Template Name</label>
                            <div class="input-group">
                                <input type="text" name="meta_template_name" id="metaTplName" class="form-control bg-light" placeholder="e.g. order_update_v1" value="<?php echo htmlspecialchars($settings['meta_template_name'] ?? ''); ?>">
                                <button type="button" class="btn btn-outline-primary" id="btnSyncTpl" title="Sync from Meta API"><i class="fas fa-sync-alt"></i></button>
                            </div>
                            <div id="tplSyncStatus" class="small mt-1 d-none"></div>
                            <small class="text-muted">Must EXACTLY match your approved Meta template name. Leave empty to use legacy text messages.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Meta Template Language</label>
                            <input type="text" name="meta_template_lang" id="metaTplLang" class="form-control bg-light" placeholder="e.g. en or en_US" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en'); ?>">
                            <small class="text-muted">Language code of approved Meta template.</small>
                        </div>
                        <div class="col-md-3 mb-3">
                             <label class="form-label fw-bold">WABA ID <small class="text-muted">(Optional)</small></label>
                             <input type="text" name="waba_id" id="metaWabaId" class="form-control bg-light" placeholder="Business Account ID" value="<?php echo htmlspecialchars($settings['waba_id'] ?? ''); ?>">
                             <small class="text-muted">Enter manually if sync fails.</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="fas fa-image text-info me-1"></i>Template Header Image URL <small class="text-muted">(Required if template has image header)</small></label>
                        <input type="url" name="wa_header_image_url" class="form-control bg-light" placeholder="https://yourdomain.com/path/to/header-image.png" value="<?php echo htmlspecialchars($settings['wa_header_image_url'] ?? ''); ?>">
                        <small class="text-muted">Public HTTPS URL of the image shown in template header. Must be a <strong>.jpg</strong> or <strong>.png</strong> file accessible from the internet.</small>
                    </div>

                    <div id="metaTemplatesList" class="mb-4 d-none p-3 border rounded-3 bg-white shadow-sm overflow-auto" style="max-height: 250px;">
                        <h6 class="fw-bold mb-2">Select Approved Template</h6>
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Language</th>
                                    <th>Category</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tplTableBody"></tbody>
                        </table>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                             <label class="form-label fw-bold mb-0">Bridge & Variable Mapping</label>
                             <div class="small text-primary fw-bold" style="cursor:help;" title="This field serves two purposes: 
1. It is the message sent in 'Web Mode'.
2. In 'API Template Mode', the ORDER of {Variables} in this text MUST match the index {{1}}, {{2}}... of your Meta Template placeholders.">
                                <i class="fas fa-info-circle me-1"></i>How mapping works?
                             </div>
                        </div>
                        <textarea name="message_template" class="form-control bg-light" rows="6" required><?php echo htmlspecialchars($settings['message_template']); ?></textarea>
                        <div class="form-text mt-2">
                            <strong>Available Variables (Dynamic Data):</strong>
                            <code>{CustomerName}</code>, <code>{OrderID}</code>, <code>{OrderStatus}</code>, <code>{TrackingID}</code>, <code>{OrderAmount}</code>
                        </div>
                    </div>

                    <!-- ===== Admin Order Notification Section ===== -->
                    <div class="border-top pt-4 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold m-0"><i class="fas fa-bell text-warning me-2"></i>Admin Order Notification</h5>
                                <small class="text-muted">Get WhatsApp alert on admin's phone when a new order is placed.</small>
                            </div>
                            <div class="form-check form-switch fs-5 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="admin_notify_on_new_order" id="enableAdminNotify" <?php echo ($settings['admin_notify_on_new_order'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2 fs-6 fw-bold" for="enableAdminNotify">Enable</label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 text-start">
                                <label class="form-label fw-bold d-block">Admin WhatsApp Number</label>
                                <?php echo render_phone_input('admin_whatsapp_number', $settings['admin_whatsapp_number'] ?? '', true); ?>
                                <small class="text-muted d-block">Admin's WhatsApp number with country code. New order alerts will be sent here.</small>
                                <button type="button" class="btn btn-sm btn-outline-success mt-2 fw-bold rounded-pill" id="btnQuickTestAdmin">
                                    <i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number
                                </button>
                                <div id="adminTestResult" class="small mt-2 d-none"></div>
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-center">
                                <div class="alert alert-success py-2 px-3 mb-0 small w-100 border-0 bg-success bg-opacity-10">
                                    <i class="fas fa-bolt text-success me-1"></i>
                                    <strong>24/7 Automated Delivery:</strong>
                                    <ul class="mb-0 ps-3 mt-1 text-dark">
                                        <li>Jaise customer ko bina "Hi" ke direct message milta hai, waise hi Admin alert bhi <strong>Approved Meta Template (<code><?php echo htmlspecialchars($settings['meta_template_name'] ?? 'new_order_status'); ?></code>)</strong> se 24/7 deliver hoga.</li>
                                        <li>Admin ko bina kisi "Hi/Hello" bhejhe har new order par automatic WhatsApp alert aayega.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ===== End Admin Order Notification Section ===== -->

                    <!-- ===== WhatsApp Chat Widget Section ===== -->
                    <div class="border-top pt-4 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold m-0"><i class="fas fa-comment-dots text-success me-2"></i>Chat Widget (Floating Button)</h5>
                                <small class="text-muted">Controls the WhatsApp chat bubble shown on your storefront.</small>
                            </div>
                            <div class="form-check form-switch fs-5 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="chat_widget_enabled" id="enableChatWidget" <?php echo ($settings['chat_widget_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2 fs-6 fw-bold" for="enableChatWidget">Enable Widget</label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 text-start">
                                <label class="form-label fw-bold d-block">Widget Phone Number</label>
                                <?php echo render_phone_input('chat_widget_number', $settings['chat_widget_number'], true); ?>
                                <small class="text-muted">Select country code and enter number.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Pre-fill Message</label>
                                <input type="text" name="chat_widget_message" class="form-control bg-light" placeholder="Hello, I have a question..." value="<?php echo htmlspecialchars($settings['chat_widget_message'] ?? 'Hello, I have a question about your products.'); ?>">
                                <small class="text-muted">Message auto-filled when customer taps the button.</small>
                            </div>
                        </div>
                    </div>
                    <!-- ===== End Chat Widget Section ===== -->

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded-3 shadow-sm me-2">
                            <i class="fas fa-save me-2"></i>Save Configuration
                        </button>
                        <button type="button" class="btn btn-outline-warning px-4 py-2 fw-bold rounded-3 shadow-sm" data-mdb-toggle="modal" data-mdb-target="#testWhatsappModal">
                            <i class="fas fa-vial me-2"></i>Test Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom pt-4 pb-3">
                <h5 class="fw-bold m-0"><i class="fas fa-history text-secondary me-2"></i>Recent Message Logs</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4">Order</th>
                                <th>Sent To</th>
                                <th>Mode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs && $logs->num_rows > 0): ?>
                                <?php while($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <a href="manage_orders.php" class="text-primary fw-bold text-decoration-none">#<?php echo $log['order_number']; ?></a>
                                        <div class="small text-muted"><?php echo htmlspecialchars($log['customer_name']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['customer_number']); ?></td>
                                    <td><span class="badge bg-<?php echo $log['sending_mode'] == 'api' ? 'info' : 'secondary'; ?>"><?php echo strtoupper($log['sending_mode']); ?></span></td>
                                    <td>
                                        <div class="text-success small fw-bold"><i class="fas fa-check-double me-1"></i><?php echo htmlspecialchars($log['status']); ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('M d, H:i', strtotime($log['sent_at'])); ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No WhatsApp messages have been sent yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/Hide password toggle
    document.getElementById('showPwWhatsapp').addEventListener('change', function() {
        const input = document.querySelector('input[name="api_token"]');
        input.type = this.checked ? 'text' : 'password';
    });

    // Sync Templates Logic
    const btnSync = document.getElementById('btnSyncTpl');
    const tplStatus = document.getElementById('tplSyncStatus');
    const tplList = document.getElementById('metaTemplatesList');
    const tplTableBody = document.getElementById('tplTableBody');

    btnSync.addEventListener('click', function() {
        const currentWabaId = document.getElementById('metaWabaId')?.value.trim() || '';
        const currentToken = document.querySelector('input[name="api_token"]')?.value.trim() || '';
        const currentPhoneId = document.querySelector('input[name="phone_number_id"]')?.value.trim() || '';

        if (!currentToken) {
            alert('Please enter your Business API Token first.');
            return;
        }

        btnSync.disabled = true;
        btnSync.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        tplStatus.className = 'small mt-1 text-info';
        tplStatus.innerText = 'Connecting to Meta Graph API...';
        tplStatus.classList.remove('d-none');
        tplList.classList.add('d-none');

        const params = new URLSearchParams({
            waba_id: currentWabaId,
            token: currentToken,
            phone_id: currentPhoneId
        });

        fetch('ajax_sync_meta_templates.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                btnSync.disabled = false;
                btnSync.innerHTML = '<i class="fas fa-sync-alt"></i>';

                if (data.error) {
                    tplStatus.className = 'small mt-1 text-danger';
                    tplStatus.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + data.error;
                } else if (data.templates && data.templates.length > 0) {
                    if (data.waba_id && document.getElementById('metaWabaId')) {
                        document.getElementById('metaWabaId').value = data.waba_id;
                    }
                    tplStatus.className = 'small mt-1 text-success';
                    tplStatus.innerHTML = `<i class="fas fa-check-circle me-1"></i> Found <strong>${data.templates.length}</strong> template(s)! Click "Select" to use.`;
                    
                    tplTableBody.innerHTML = '';
                    data.templates.forEach(tpl => {
                        const statusBadge = tpl.status === 'APPROVED' 
                            ? '<span class="badge bg-success">APPROVED</span>' 
                            : `<span class="badge bg-warning text-dark">${tpl.status}</span>`;
                        
                        const bodySnippet = tpl.body_text 
                            ? `<div class="small text-muted font-monospace mt-1 text-truncate" style="max-width:280px;" title="${tpl.body_text.replace(/"/g, '&quot;')}">${tpl.body_text}</div>` 
                            : '';

                        const row = `
                            <tr>
                                <td>
                                    <div class="fw-bold">${tpl.name}</div>
                                    ${bodySnippet}
                                </td>
                                <td><span class="badge bg-light text-dark border">${tpl.language}</span></td>
                                <td>${statusBadge}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary py-1 px-3 fw-bold rounded-pill" onclick="selectTemplate('${tpl.name}', '${tpl.language}')">Select</button>
                                </td>
                            </tr>
                        `;
                        tplTableBody.insertAdjacentHTML('beforeend', row);
                    });
                    tplList.classList.remove('d-none');
                } else {
                    tplStatus.className = 'small mt-1 text-warning';
                    tplStatus.innerHTML = '<i class="fas fa-info-circle me-1"></i> No message templates found in this WhatsApp Business Account.';
                }
            })
            .catch(err => {
                btnSync.disabled = false;
                btnSync.innerHTML = '<i class="fas fa-sync-alt"></i>';
                tplStatus.className = 'small mt-1 text-danger';
                tplStatus.innerText = 'Network error: ' + err.message;
            });
    });
});

function selectTemplate(name, lang) {
    document.getElementById('metaTplName').value = name;
    document.getElementById('metaTplLang').value = lang;
    document.getElementById('metaTemplatesList').classList.add('d-none');
    document.getElementById('tplSyncStatus').innerText = 'Template selected: ' + name;
}
</script>

<!-- Test WhatsApp Modal -->
<div class="modal fade" id="testWhatsappModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-primary"><i class="fas fa-paper-plane me-2"></i>Send Test Notification</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Testing will use a most recent order to populate variables like {CustomerName}.</p>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase tracking-wider">Recipient Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-phone-alt text-muted"></i></span>
                        <input type="text" id="testPhone" class="form-control border-start-0 py-2" placeholder="e.g. 919876543210" value="">
                    </div>
                    <div class="form-text mt-2" style="font-size:0.75rem;">
                        <i class="fas fa-info-circle me-1"></i> Include country code (e.g. <strong>91</strong> for India). Do NOT include +.
                    </div>
                </div>
                <div id="testResult" class="d-none mb-3"></div>
                <button type="button" id="btnRunTest" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm border-0">
                    Send Test Message
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnRunTest = document.getElementById('btnRunTest');
    const testPhone = document.getElementById('testPhone');
    const testResult = document.getElementById('testResult');

    btnRunTest.addEventListener('click', function() {
        const phone = testPhone.value.replace(/\D/g, '');
        if (!phone) { alert('Please enter a valid number (e.g. 919876543210)'); return; }

        btnRunTest.disabled = true;
        btnRunTest.innerText = 'Sending...';
        testResult.className = 'alert alert-info py-2 small';
        testResult.innerText = 'Calling API...';
        testResult.classList.remove('d-none');

        // Note: we use order_id=1 or latest order if possible. Here we just hardcode a placeholder.
        fetch('ajax_log_whatsapp.php?test=1&number=' + phone)
            .then(res => res.text()) // Get raw text first to handle PHP errors
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
                
                btnRunTest.disabled = false;
                btnRunTest.innerText = 'Send Test Message';
                if (data.success) {
                    const msgId = data.message_id ? `<br><small class="text-muted">Message ID: ${data.message_id}</small>` : '';
                    const msgStatus = data.message_status ? `<br><small class="text-muted">Status: <strong>${data.message_status}</strong></small>` : '';
                    testResult.className = 'alert alert-success py-2 small';
                    testResult.innerHTML = `✅ <strong>API Accepted!</strong> Message queued for delivery.${msgId}${msgStatus}`
                        + `<br><small class="text-muted mt-1 d-block">If NOT received: check <a href="whatsapp_debug.php" target="_blank">WhatsApp Debug Tool</a></small>`;
                } else {
                    const errCode = data.error_code ? ` [Code: ${data.error_code}]` : '';
                    const details = data.details ? `<br><small>${data.details}</small>` : '';
                    testResult.className = 'alert alert-danger py-2 small';
                    testResult.innerHTML = `❌ <strong>FAILED${errCode}:</strong> ${data.error || 'Unknown error'}${details}`
                        + `<br><small class="mt-1 d-block"><a href="whatsapp_debug.php" target="_blank">→ Open Debug Tool for full details</a></small>`;
                }
            })
            .catch(err => {
                btnRunTest.disabled = false;
                btnRunTest.innerText = 'Send Test Message';
                testResult.className = 'alert alert-danger py-2 small';
                testResult.innerText = 'Network error: ' + err.message;
            });
    });

    // Quick Test Admin Alert Handler
    const btnQuickTestAdmin = document.getElementById('btnQuickTestAdmin');
    const adminTestResult   = document.getElementById('adminTestResult');

    if (btnQuickTestAdmin) {
        btnQuickTestAdmin.addEventListener('click', function() {
            // Read admin number from the hidden phone input or text input
            const adminPhoneInput = document.querySelector('input[name="admin_whatsapp_number"]') || document.querySelector('.phone-hidden-final');
            let rawNumber = adminPhoneInput ? adminPhoneInput.value : '';
            
            if (!rawNumber || rawNumber.replace(/\D/g, '').length < 10) {
                alert('Please enter a valid Admin WhatsApp Number (at least 10 digits) before testing.');
                return;
            }

            btnQuickTestAdmin.disabled = true;
            btnQuickTestAdmin.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending Test Alert...';
            adminTestResult.className = 'alert alert-info py-2 small mt-2';
            adminTestResult.innerText = 'Calling Meta Cloud API...';
            adminTestResult.classList.remove('d-none');

            fetch('ajax_log_whatsapp.php?test_admin=1&number=' + encodeURIComponent(rawNumber))
                .then(res => res.text())
                .then(text => {
                    btnQuickTestAdmin.disabled = false;
                    btnQuickTestAdmin.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number';
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch(e) {
                        adminTestResult.className = 'alert alert-danger py-2 small mt-2';
                        adminTestResult.innerText = 'Invalid response: ' + text.substring(0, 150);
                        return;
                    }

                    if (data.success) {
                        const msgId = data.message_id ? `<span class="badge bg-success ms-1">${data.message_id}</span>` : '';
                        adminTestResult.className = 'alert alert-success py-3 small mt-2';
                        adminTestResult.innerHTML = `
                            <div><i class="fas fa-check-circle me-1"></i> <strong>Meta API Accepted Message!</strong> ${msgId}</div>
                            <div class="mt-2 pt-2 border-top border-success border-opacity-25 text-dark" style="font-size:0.82rem; line-height:1.45;">
                                <strong>⚠️ Agar WhatsApp par turant receive na ho, toh ye 2 baatein check karein:</strong>
                                <ol class="mb-0 ps-3 mt-1 text-dark">
                                    <li><strong>24-Hour Window Rule:</strong> Apne personal phone (<code>${rawNumber}</code>) se apne Business number (<strong>+91 9721083003</strong>) par WhatsApp par <strong>"Hi"</strong> message send kijiye taki Meta incoming session open ho sake.</li>
                                    <li><strong>Meta App "Development Mode":</strong> Agar Meta Developer Console (<code>developers.facebook.com</code>) me aapka App <em>Development Mode</em> me hai, toh <em>WhatsApp &gt; API Setup &gt; Step 5</em> me <code>${rawNumber}</code> number add hona zaroori hai, ya App ko <strong>Live Mode</strong> me switch karein.</li>
                                </ol>
                                <div class="mt-2 text-end">
                                    <a href="whatsapp_debug.php" target="_blank" class="fw-bold text-success text-decoration-underline"><i class="fas fa-external-link-alt me-1"></i>Open Full Meta Debug Log</a>
                                </div>
                            </div>
                        `;
                    } else {
                        adminTestResult.className = 'alert alert-danger py-2 small mt-2';
                        adminTestResult.innerHTML = `❌ <strong>Alert Failed:</strong> ${data.error || 'Unknown error'}`
                            + (data.details ? `<br><small>${data.details}</small>` : '')
                            + `<br><small class="mt-1 d-block">Check <a href="whatsapp_debug.php" target="_blank" class="alert-link">WhatsApp Debug Tool</a> for details.</small>`;
                    }
                })
                .catch(err => {
                    btnQuickTestAdmin.disabled = false;
                    btnQuickTestAdmin.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number';
                    adminTestResult.className = 'alert alert-danger py-2 small mt-2';
                    adminTestResult.innerText = 'Network error: ' + err.message;
                });
        });
    }
});
</script>

<?php require_once 'admin_footer.php'; ?>
