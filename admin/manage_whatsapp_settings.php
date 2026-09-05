<?php
require_once 'admin_header.php';
require_once __DIR__ . '/../includes/whatsapp_functions.php';

// Ensure all settings columns exist (safe migration on load)
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_enabled TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_number VARCHAR(20) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS chat_widget_message VARCHAR(255) NOT NULL DEFAULT 'Hello, I have a question about your products.'");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS phone_number_id VARCHAR(50) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS meta_template_name VARCHAR(100) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS meta_template_lang VARCHAR(10) NOT NULL DEFAULT 'en'");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS waba_id VARCHAR(50) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS wa_header_image_url VARCHAR(500) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_whatsapp_number VARCHAR(20) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_notify_on_new_order TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_template_name VARCHAR(100) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_template_name VARCHAR(100) NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_message_template TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_status_notify_enabled TINYINT(1) NOT NULL DEFAULT 1");

// Fetch current settings
$settings_query = "SELECT * FROM whatsapp_settings WHERE id = 1";
$result = $conn->query($settings_query);
$settings = $result ? $result->fetch_assoc() : null;

$default_order_confirm_tpl = "Hello Dear {CustomerName},\n\nThank you for your order! Your Order #{OrderID} has been successfully placed.\n\nOrder Date: {OrderDate}\nTotal Amount: ₹{OrderAmount}\nPayment Method: {PaymentMethod}\n\nDelivery Address:\n{DeliveryAddress}\n\nThank you for shopping with Sagar Starter's!";

if (!$settings) {
    // Failsafe insert if table is empty
    $conn->query("INSERT IGNORE INTO whatsapp_settings (id, message_template, order_confirmation_message_template) VALUES (1, 'Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\nThank you for shopping with us.', '" . $conn->real_escape_string($default_order_confirm_tpl) . "')");
    $settings = [
        'is_enabled' => 1,
        'sender_number' => '',
        'api_token' => '',
        'sending_mode' => 'web',
        'message_template' => "Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\nThank you for shopping with us.",
        'order_confirmation_enabled' => 1,
        'order_confirmation_template_name' => '',
        'order_confirmation_message_template' => $default_order_confirm_tpl,
        'order_status_notify_enabled' => 1,
        'chat_widget_enabled' => 1,
        'chat_widget_number' => '',
        'chat_widget_message' => 'Hello, I have a question about your products.'
    ];
}

if (empty($settings['order_confirmation_message_template'])) {
    $settings['order_confirmation_message_template'] = $default_order_confirm_tpl;
}

$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $sender_number    = $conn->real_escape_string($_POST['sender_number'] ?? '');
    $api_token        = $conn->real_escape_string($_POST['api_token'] ?? '');
    $phone_number_id  = $conn->real_escape_string($_POST['phone_number_id'] ?? '');
    $sending_mode     = $conn->real_escape_string($_POST['sending_mode'] ?? 'api');
    $is_enabled       = isset($_POST['is_enabled']) ? 1 : 0;

    // Customer Order Confirmation Fields
    $order_confirmation_enabled          = isset($_POST['order_confirmation_enabled']) ? 1 : 0;
    $order_confirmation_template_name    = $conn->real_escape_string(trim($_POST['order_confirmation_template_name'] ?? ''));
    $order_confirmation_message_template = $conn->real_escape_string($_POST['order_confirmation_message_template'] ?? '');

    // Customer Order Status Update Fields
    $order_status_notify_enabled = isset($_POST['order_status_notify_enabled']) ? 1 : 0;
    $meta_template_name          = $conn->real_escape_string(trim($_POST['meta_template_name'] ?? ''));
    $message_template            = $conn->real_escape_string($_POST['message_template'] ?? '');

    // General Meta API Meta Fields
    $meta_template_lang = $conn->real_escape_string(trim($_POST['meta_template_lang'] ?? 'en'));
    $waba_id            = $conn->real_escape_string(trim($_POST['waba_id'] ?? ''));
    $wa_header_image_url = $conn->real_escape_string(trim($_POST['wa_header_image_url'] ?? ''));

    // Admin Notification Fields
    $admin_whatsapp_number     = $conn->real_escape_string(trim($_POST['admin_whatsapp_number'] ?? ''));
    $admin_notify_on_new_order = isset($_POST['admin_notify_on_new_order']) ? 1 : 0;
    $admin_template_name       = $conn->real_escape_string(trim($_POST['admin_template_name'] ?? ''));

    // Chat Widget fields
    $chat_widget_enabled = isset($_POST['chat_widget_enabled']) ? 1 : 0;
    $chat_widget_number  = $conn->real_escape_string(trim($_POST['chat_widget_number'] ?? ''));
    $chat_widget_message = $conn->real_escape_string($_POST['chat_widget_message'] ?? 'Hello, I have a question about your products.');

    $update_query = "UPDATE whatsapp_settings SET 
        is_enabled = $is_enabled,
        sender_number = '$sender_number',
        api_token = '$api_token',
        sending_mode = '$sending_mode',
        phone_number_id = '$phone_number_id',
        meta_template_lang = '$meta_template_lang',
        waba_id = '$waba_id',
        wa_header_image_url = '$wa_header_image_url',
        order_confirmation_enabled = $order_confirmation_enabled,
        order_confirmation_template_name = '$order_confirmation_template_name',
        order_confirmation_message_template = '$order_confirmation_message_template',
        order_status_notify_enabled = $order_status_notify_enabled,
        meta_template_name = '$meta_template_name',
        message_template = '$message_template',
        admin_whatsapp_number = '$admin_whatsapp_number',
        admin_notify_on_new_order = $admin_notify_on_new_order,
        admin_template_name = '$admin_template_name',
        chat_widget_enabled = $chat_widget_enabled,
        chat_widget_number = '$chat_widget_number',
        chat_widget_message = '$chat_widget_message'
        WHERE id = 1";

    if ($conn->query($update_query)) {
        $success_msg = "WhatsApp Settings updated successfully.";
        // Refresh settings
        $result = $conn->query($settings_query);
        $settings = $result->fetch_assoc();
    } else {
        $error_msg = "Database Error: " . $conn->error;
    }
}

// Fetch logs
$logs_query = "SELECT wl.*, o.id as order_number, u.name as customer_name FROM whatsapp_logs wl LEFT JOIN orders o ON wl.order_id = o.id LEFT JOIN users u ON o.user_id = u.id ORDER BY wl.sent_at DESC LIMIT 50";
$logs = $conn->query($logs_query);
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fab fa-whatsapp"></i> Instant Messaging Hub
            </div>
            <h1 class="adm-hero-title">WhatsApp Notifications & Alerts</h1>
            <p class="adm-hero-subtitle">Automate customer order confirmations, shipping updates, 24/7 admin purchase alerts, and storefront live chat.</p>
        </div>
        <div class="adm-hero-actions">
            <button type="button" class="adm-btn-white" onclick="openMetaTemplatePicker('meta_template_name')">
                <i class="fas fa-cloud-download-alt me-2 text-success"></i>Fetch Meta Templates
            </button>
            <a href="whatsapp_debug.php" target="_blank" class="adm-btn-white">
                <i class="fas fa-bug me-2 text-muted"></i>API Diagnostics
            </a>
        </div>
    </div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
    <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="update_settings">

    <div class="row">
        <!-- Left Column: Settings Configuration -->
        <div class="col-lg-8 col-md-12">
            
            <!-- 1. GENERAL & META API CREDENTIALS CARD -->
            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-plug text-primary me-2"></i>Meta Cloud API Credentials</h5>
                        <small class="text-muted">Official WhatsApp Business Platform connection.</small>
                    </div>
                    <div class="form-check form-switch fs-5 m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" id="enableWhatsapp" <?php echo (!empty($settings['is_enabled'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label ms-2 fs-6 fw-bold" for="enableWhatsapp">Enable Feature</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Sending Mode</label>
                        <select name="sending_mode" class="form-select bg-light">
                            <option value="api" <?php echo ($settings['sending_mode'] === 'api') ? 'selected' : ''; ?>>WhatsApp Business API (Automated 24/7 Delivery)</option>
                            <option value="web" <?php echo ($settings['sending_mode'] === 'web') ? 'selected' : ''; ?>>WhatsApp Web (Manual Redirect)</option>
                        </select>
                        <small class="text-muted">API mode enables 24/7 automated delivery directly via official Meta API.</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 text-start">
                            <label class="form-label fw-bold d-block">Sender Number</label>
                            <?php echo render_phone_input('sender_number', $settings['sender_number'], true); ?>
                        </div>
                        <div class="col-md-4 mb-3 text-start">
                            <label class="form-label fw-bold d-block">Phone Number ID (Meta Graph)</label>
                            <input type="text" name="phone_number_id" id="phone_number_id_input" class="form-control bg-light" placeholder="E.g. 1045612345678" value="<?php echo htmlspecialchars($settings['phone_number_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Business API Token</label>
                            <input type="password" name="api_token" id="api_token_input" class="form-control bg-light" placeholder="EAAI..." value="<?php echo htmlspecialchars($settings['api_token'] ?? ''); ?>">
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwWhatsapp">
                                <label class="form-check-label small text-muted" for="showPwWhatsapp">Show Token</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">WABA ID <small class="text-muted">(Optional)</small></label>
                            <input type="text" name="waba_id" id="metaWabaId" class="form-control bg-light" placeholder="Business Account ID" value="<?php echo htmlspecialchars($settings['waba_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Meta Template Language</label>
                            <input type="text" name="meta_template_lang" id="metaTplLang" class="form-control bg-light" placeholder="e.g. en or en_US" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Template Header Image <small class="text-muted">(Optional)</small></label>
                            <input type="url" id="waHeaderImgInput" name="wa_header_image_url" class="form-control bg-light" placeholder="https://..." value="<?php echo htmlspecialchars($settings['wa_header_image_url'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. CUSTOMER NOTIFICATIONS CARD (TWO TEMPLATES) -->
            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold m-0 text-dark"><i class="fas fa-users text-success me-2"></i>Customer WhatsApp Notification Templates</h5>
                    <small class="text-muted">Customer ko bheje jaane wale 2 alag-alag Meta Approved Templates.</small>
                </div>
                <div class="card-body p-4">
                    
                    <!-- ── TEMPLATE 1: CUSTOMER ORDER CONFIRMATION ── -->
                    <div class="p-3 border rounded-3 bg-light bg-opacity-50 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-primary px-2 py-1 mb-1">1. New Order Confirmation</span>
                                <h6 class="fw-bold m-0 text-dark">Order Confirmation Template (Customer)</h6>
                                <small class="text-muted">Jab bhi koi customer naya order place karega, ye Meta template turant send hoga.</small>
                            </div>
                            <div class="form-check form-switch fs-5 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="order_confirmation_enabled" id="enableOrderConfirm" <?php echo (!empty($settings['order_confirmation_enabled'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2 small fw-bold" for="enableOrderConfirm">Enable</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase tracking-wider">Meta Approved Template Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fab fa-whatsapp text-success"></i></span>
                                <input type="text" name="order_confirmation_template_name" id="orderConfirmTplInput" class="form-control bg-white" placeholder="e.g. order_confirmation" value="<?php echo htmlspecialchars($settings['order_confirmation_template_name'] ?? ''); ?>">
                                <button type="button" class="btn btn-outline-primary fw-bold" onclick="openMetaTemplatePicker('orderConfirmTplInput')">
                                    <i class="fas fa-search me-1"></i> Fetch from Meta
                                </button>
                            </div>
                            <div class="form-text small">Meta WhatsApp Manager me approved template ka exact name (e.g. <code>order_confirmation</code> ya <code>admin_new_order_alert</code>).</div>
                        </div>

                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small mb-0">Bridge & Fallback Message Template</label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{CustomerName}')">+Name</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{OrderID}')">+OrderID</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{OrderDate}')">+Date</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{OrderAmount}')">+Amount</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{PaymentMethod}')">+Payment</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{DeliveryAddress}')">+Address</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('orderConfirmMsgInput', '{ItemsOrdered}')">+Items</button>
                                </div>
                            </div>
                            <textarea name="order_confirmation_message_template" id="orderConfirmMsgInput" class="form-control bg-white" rows="5"><?php echo htmlspecialchars($settings['order_confirmation_message_template'] ?? $default_order_confirm_tpl); ?></textarea>
                            <div class="form-text small">
                                <strong>Dynamic Variables:</strong> <code>{CustomerName}</code>, <code>{OrderID}</code>, <code>{OrderDate}</code>, <code>{OrderTime}</code>, <code>{OrderAmount}</code>, <code>{PaymentMethod}</code>, <code>{DeliveryAddress}</code>, <code>{ItemsOrdered}</code>
                            </div>
                        </div>

                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold" onclick="openTestModal('order_confirm')">
                                <i class="fas fa-paper-plane me-1"></i> Send Test Order Confirmation
                            </button>
                        </div>
                    </div>

                    <!-- ── TEMPLATE 2: CUSTOMER ORDER STATUS UPDATE ── -->
                    <div class="p-3 border rounded-3 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-info text-dark px-2 py-1 mb-1">2. Status Update</span>
                                <h6 class="fw-bold m-0 text-dark">Order Status Update Template (Customer)</h6>
                                <small class="text-muted">Jab Admin order status change karega (Shipped, Delivered etc.), tab ye template send hoga.</small>
                            </div>
                            <div class="form-check form-switch fs-5 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="order_status_notify_enabled" id="enableOrderStatusNotify" <?php echo (!empty($settings['order_status_notify_enabled'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2 small fw-bold" for="enableOrderStatusNotify">Enable</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase tracking-wider">Meta Approved Template Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fab fa-whatsapp text-info"></i></span>
                                <input type="text" name="meta_template_name" id="statusTplInput" class="form-control bg-white" placeholder="e.g. new_order_status" value="<?php echo htmlspecialchars($settings['meta_template_name'] ?? ''); ?>">
                                <button type="button" class="btn btn-outline-primary fw-bold" onclick="openMetaTemplatePicker('statusTplInput')">
                                    <i class="fas fa-search me-1"></i> Fetch from Meta
                                </button>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                                <span class="small text-muted fw-bold">Quick Select:</span>
                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 rounded-pill small" onclick="document.getElementById('statusTplInput').value='new_order_status'">
                                    <i class="fas fa-check-circle me-1"></i> new_order_status (5 Params - Tested)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill small" onclick="document.getElementById('statusTplInput').value='order_confirmation'">
                                    <i class="fas fa-check-circle me-1"></i> order_confirmation (9 Params - Working)
                                </button>
                            </div>
                            <div class="form-text small mt-1">Meta WhatsApp Manager me approved status template ka name (e.g. <code>new_order_status</code> ya <code>order_status_update</code>).</div>
                        </div>

                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small mb-0">Bridge & Fallback Message Template</label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('statusMsgInput', '{CustomerName}')">+Name</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('statusMsgInput', '{OrderID}')">+OrderID</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('statusMsgInput', '{OrderStatus}')">+Status</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('statusMsgInput', '{TrackingID}')">+Tracking</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="insertVar('statusMsgInput', '{OrderAmount}')">+Amount</button>
                                </div>
                            </div>
                            <textarea name="message_template" id="statusMsgInput" class="form-control bg-white" rows="5"><?php echo htmlspecialchars($settings['message_template'] ?? ''); ?></textarea>
                            <div class="form-text small">
                                <strong>Dynamic Variables:</strong> <code>{CustomerName}</code>, <code>{OrderID}</code>, <code>{OrderStatus}</code>, <code>{TrackingID}</code>, <code>{OrderAmount}</code>
                            </div>
                        </div>

                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill fw-bold" onclick="openTestModal('status_update')">
                                <i class="fas fa-paper-plane me-1"></i> Send Test Status Update
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 3. ADMIN ORDER NOTIFICATION CARD -->
            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-bell text-warning me-2"></i>Admin New Order WhatsApp Alert</h5>
                        <small class="text-muted">Naye order aane par admin ke personal WhatsApp number par instant alert.</small>
                    </div>
                    <div class="form-check form-switch fs-5 m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="admin_notify_on_new_order" id="enableAdminNotify" <?php echo (!empty($settings['admin_notify_on_new_order'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label ms-2 fs-6 fw-bold" for="enableAdminNotify">Enable</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3 text-start">
                            <label class="form-label fw-bold d-block">Admin WhatsApp Number</label>
                            <?php echo render_phone_input('admin_whatsapp_number', $settings['admin_whatsapp_number'] ?? '', true); ?>
                            <?php 
                                $fn_norm = function_exists('normalize_whatsapp_phone_number') 
                                    ? 'normalize_whatsapp_phone_number' 
                                    : function($p) { $d = preg_replace('/[^0-9]/', '', (string)$p); return (strlen($d) == 10 ? '91'.$d : $d); };
                                $clean_adm_num = $fn_norm($settings['admin_whatsapp_number'] ?? '');
                                $clean_snd_num = $fn_norm($settings['sender_number'] ?? '');
                                $is_same_as_sender = (!empty($clean_adm_num) && !empty($clean_snd_num) && $clean_adm_num === $clean_snd_num);
                            ?>
                            <div class="small mt-1">
                                <span class="text-muted">Admin ka Personal/Alternate WhatsApp number (e.g. <code>+91 9876543210</code>).</span>
                                <div id="sameNumberWarningBox" class="alert alert-warning border border-warning py-2 px-3 mt-2 mb-1 small rounded-3 <?php echo $is_same_as_sender ? '' : 'd-none'; ?>">
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    <strong>Self-Messaging Rule:</strong> Yeh number Store ke API Sender number (<code>+<?php echo htmlspecialchars($clean_snd_num); ?></code>) se <strong>alag</strong> hona chahiye. Meta WhatsApp Cloud API ek number se <u>usi number par</u> message deliver nahi karta.
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label fw-bold small text-uppercase tracking-wider">Admin Meta Template Name</label>
                                <div class="input-group">
                                    <input type="text" name="admin_template_name" id="adminTplInput" class="form-control bg-light" placeholder="e.g. order_confirmation" value="<?php echo htmlspecialchars(!empty($settings['admin_template_name']) ? $settings['admin_template_name'] : 'order_confirmation'); ?>">
                                    <button type="button" class="btn btn-outline-primary" onclick="openMetaTemplatePicker('adminTplInput')">
                                        <i class="fas fa-search me-1"></i> Fetch
                                    </button>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                                    <span class="small text-muted fw-bold">Recommended:</span>
                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 rounded-pill small" onclick="document.getElementById('adminTplInput').value='order_confirmation'">
                                        <i class="fas fa-check-circle me-1"></i> Use 'order_confirmation' (100% Working)
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">Approved template in Meta (default <code>order_confirmation</code> use karne par 24/7 bina issue alert aayega).</small>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-outline-success fw-bold rounded-pill" id="btnQuickTestAdmin">
                                    <i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-pill" onclick="openTestModal('admin_alert')">
                                    <i class="fas fa-paper-plane me-1"></i> Test on Another Number
                                </button>
                            </div>
                            <div id="adminTestResult" class="small mt-2 d-none"></div>
                        </div>

                        <div class="col-md-6 mb-3 d-flex align-items-center">
                            <div class="alert alert-success py-3 px-3 mb-0 small w-100 border-0 bg-success bg-opacity-10 rounded-3">
                                <h6 class="fw-bold text-success mb-2"><i class="fas fa-bolt me-1"></i> 24/7 Automated Delivery</h6>
                                <p class="mb-2 text-dark">Meta approved template use karne par 24/7 bina kisi customer chat session ke instant admin alert deliver hota hai.</p>
                                <hr class="my-2 border-success border-opacity-25">
                                <strong>Supported Templates:</strong>
                                <div class="bg-white p-2 rounded border small text-muted mt-1" style="font-size:0.78rem;">
                                    <div class="mb-1"><span class="badge bg-success">Recommended</span> <strong>order_confirmation:</strong> Yeh template Meta me approved hai. Isme Customer Name, Order ID, Date, Amount, Payment, Status, Items, Address aur Admin Link sab receive hota hai!</div>
                                    <div><span class="badge bg-secondary">Dedicated</span> <strong>admin_new_order_alert:</strong> 11-Parameter dedicated template (agar Meta me approve karwaya ho).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. CHAT WIDGET CARD -->
            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-comment-dots text-success me-2"></i>Storefront WhatsApp Chat Widget</h5>
                        <small class="text-muted">Controls the floating WhatsApp chat button on your customer website.</small>
                    </div>
                    <div class="form-check form-switch fs-5 m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="chat_widget_enabled" id="enableChatWidget" <?php echo (!empty($settings['chat_widget_enabled'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label ms-2 fs-6 fw-bold" for="enableChatWidget">Enable Widget</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3 text-start">
                            <label class="form-label fw-bold d-block">Support WhatsApp Number</label>
                            <?php echo render_phone_input('chat_widget_number', $settings['chat_widget_number'] ?? '', true); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pre-filled Chat Message</label>
                            <input type="text" name="chat_widget_message" class="form-control bg-light" placeholder="Hello, I have a question..." value="<?php echo htmlspecialchars($settings['chat_widget_message'] ?? 'Hello, I have a question about your products.'); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="text-end mb-5">
                <button type="submit" class="btn btn-primary px-5 py-3 fw-bold rounded-pill shadow">
                    <i class="fas fa-save me-2"></i> Save All WhatsApp Configurations
                </button>
            </div>

        </div>

        <!-- Right Column: Live Message Logs -->
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="fas fa-history text-secondary me-2"></i>Recent Message Logs</h5>
                    <span class="badge bg-light text-dark border">Last 50</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="ps-3">Order</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($logs && $logs->num_rows > 0): ?>
                                    <?php while($log = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <a href="manage_orders.php" class="text-primary fw-bold text-decoration-none">#<?php echo $log['order_number'] ?? $log['order_id']; ?></a>
                                            <div class="small text-muted text-truncate" style="max-width:90px;"><?php echo htmlspecialchars($log['customer_name'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($log['customer_number']); ?></div>
                                            <span class="badge bg-<?php echo $log['sending_mode'] == 'api' ? 'info' : 'secondary'; ?>" style="font-size:0.65rem;"><?php echo strtoupper($log['sending_mode']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $is_err = stripos($log['status'], 'Failed') !== false;
                                            $badge_cls = $is_err ? 'text-danger' : 'text-success';
                                            $icon = $is_err ? 'fa-times-circle' : 'fa-check-double';
                                            ?>
                                            <div class="<?php echo $badge_cls; ?> fw-bold text-wrap" style="word-break:break-word; max-width:130px; font-size:0.75rem;">
                                                <i class="fas <?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars($log['status']); ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d, H:i', strtotime($log['sent_at'])); ?></div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">No WhatsApp logs yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ===== UNIVERSAL META TEMPLATE PICKER MODAL ===== -->
<div class="modal fade" id="metaTemplatePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-4">
                <div>
                    <h5 class="modal-title fw-bold text-primary"><i class="fab fa-whatsapp me-2"></i>Select Meta Approved Template</h5>
                    <small class="text-muted">Live sync from your WhatsApp Business Account (WABA).</small>
                </div>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <input type="text" id="tplSearchFilter" class="form-control form-control-sm w-50 bg-light" placeholder="🔍 Search template by name...">
                    <button type="button" id="btnRefreshModalTpl" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">
                        <i class="fas fa-sync-alt me-1"></i> Refresh from Meta
                    </button>
                </div>
                
                <div id="modalTplStatus" class="alert alert-info py-2 small d-none"></div>

                <div class="table-responsive border rounded-3 bg-white" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Template Name & Preview</th>
                                <th>Lang</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modalTplTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Click "Refresh from Meta" to load templates.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TEST NOTIFICATION MODAL ===== -->
<div class="modal fade" id="universalTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-primary" id="testModalTitle"><i class="fas fa-paper-plane me-2"></i>Send Test Notification</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3" id="testModalDesc">Send a test notification to verify Meta delivery.</p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase tracking-wider">Recipient Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-phone-alt text-muted"></i></span>
                        <input type="text" id="universalTestPhone" class="form-control py-2" placeholder="e.g. 919876543210" value="">
                    </div>
                    <div class="form-text small mt-1">Include country code (e.g. <strong>91</strong> for India).</div>
                </div>
                <div id="universalTestResult" class="d-none mb-3"></div>
                <button type="button" id="btnRunUniversalTest" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm border-0">
                    Send Test Message
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTargetInputId = '';
let currentTestType = 'order_confirm';

function insertVar(textareaId, tag) {
    const el = document.getElementById(textareaId);
    if (!el) return;
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const val = el.value;
    el.value = val.substring(0, start) + tag + val.substring(end);
    el.selectionStart = el.selectionEnd = start + tag.length;
    el.focus();
}

function openMetaTemplatePicker(targetInputId) {
    currentTargetInputId = targetInputId;
    const modalEl = document.getElementById('metaTemplatePickerModal');
    const modal = new mdb.Modal(modalEl);
    modal.show();
    fetchMetaTemplates();
}

function fetchMetaTemplates() {
    const statusEl = document.getElementById('modalTplStatus');
    const tbodyEl = document.getElementById('modalTplTableBody');
    const token = document.getElementById('api_token_input')?.value.trim() || '';
    const phoneId = document.getElementById('phone_number_id_input')?.value.trim() || '';
    const wabaId = document.getElementById('metaWabaId')?.value.trim() || '';

    if (!token) {
        statusEl.className = 'alert alert-danger py-2 small';
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Please enter your Business API Token first.';
        statusEl.classList.remove('d-none');
        return;
    }

    statusEl.className = 'alert alert-info py-2 small';
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Connecting to Meta Graph API...';
    statusEl.classList.remove('d-none');

    const params = new URLSearchParams({ token, phone_id: phoneId, waba_id: wabaId });

    fetch('ajax_sync_meta_templates.php?' + params.toString())
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                statusEl.className = 'alert alert-danger py-2 small';
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + data.error;
            } else if (data.templates && data.templates.length > 0) {
                if (data.waba_id && document.getElementById('metaWabaId')) {
                    document.getElementById('metaWabaId').value = data.waba_id;
                }
                statusEl.className = 'alert alert-success py-2 small';
                statusEl.innerHTML = `<i class="fas fa-check-circle me-1"></i> Found <strong>${data.templates.length}</strong> template(s) in Meta Account!`;

                tbodyEl.innerHTML = '';
                data.templates.forEach(tpl => {
                    const isApproved = tpl.status === 'APPROVED';
                    const statusBadge = isApproved 
                        ? '<span class="badge bg-success">APPROVED</span>' 
                        : `<span class="badge bg-warning text-dark">${tpl.status}</span>`;

                    const paramBadge = (tpl.param_count && tpl.param_count > 0)
                        ? `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1">${tpl.param_count} params</span>`
                        : '';
                    const headerBadge = (tpl.header_type && tpl.header_type !== 'NONE')
                        ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1"><i class="fas fa-image me-1"></i>${tpl.header_type}</span>`
                        : '';

                    const bodyText = tpl.body_text || '';
                    const bodyPreview = bodyText ? `<div class="small text-muted font-monospace mt-1 text-truncate" style="max-width:320px;" title="${bodyText.replace(/"/g, '&quot;')}">${bodyText}</div>` : '';

                    const row = `
                        <tr class="tpl-row" data-name="${tpl.name.toLowerCase()}">
                            <td>
                                <div class="fw-bold text-dark d-flex align-items-center">
                                    ${tpl.name} ${paramBadge} ${headerBadge}
                                </div>
                                ${bodyPreview}
                            </td>
                            <td><span class="badge bg-light text-dark border">${tpl.language}</span></td>
                            <td>${statusBadge}</td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-primary py-1 px-3 fw-bold rounded-pill" onclick="applyTemplate('${tpl.name}', '${tpl.language}')">
                                    Select
                                </button>
                            </td>
                        </tr>
                    `;
                    tbodyEl.insertAdjacentHTML('beforeend', row);
                });
            } else {
                statusEl.className = 'alert alert-warning py-2 small';
                statusEl.innerHTML = '<i class="fas fa-info-circle me-1"></i> No templates found in this Meta WhatsApp Business Account.';
            }
        })
        .catch(err => {
            statusEl.className = 'alert alert-danger py-2 small';
            statusEl.innerText = 'Network error: ' + err.message;
        });
}

function applyTemplate(name, lang) {
    if (currentTargetInputId && document.getElementById(currentTargetInputId)) {
        document.getElementById(currentTargetInputId).value = name;
    }
    if (document.getElementById('metaTplLang')) {
        document.getElementById('metaTplLang').value = lang;
    }
    const modalEl = document.getElementById('metaTemplatePickerModal');
    const modalInstance = mdb.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();
}

function openTestModal(type) {
    currentTestType = type;
    const modalTitle = document.getElementById('testModalTitle');
    const modalDesc  = document.getElementById('testModalDesc');
    const resEl      = document.getElementById('universalTestResult');
    resEl.classList.add('d-none');

    if (type === 'order_confirm') {
        modalTitle.innerHTML = '<i class="fas fa-cart-arrow-down me-2 text-primary"></i>Test Order Confirmation';
        modalDesc.innerText = 'Send a simulated Order Confirmation WhatsApp notification using the current template and variables.';
    } else if (type === 'admin_alert') {
        modalTitle.innerHTML = '<i class="fas fa-bell me-2 text-warning"></i>Test Admin Order Alert';
        modalDesc.innerText = 'Send a simulated Admin New Order Alert WhatsApp notification to ANY phone number to verify template delivery.';
    } else {
        modalTitle.innerHTML = '<i class="fas fa-truck me-2 text-info"></i>Test Order Status Update';
        modalDesc.innerText = 'Send a simulated Order Status Update WhatsApp notification using the current status template.';
    }

    const modalEl = document.getElementById('universalTestModal');
    const modal = new mdb.Modal(modalEl);
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Show/Hide password toggle
    const pwToggle = document.getElementById('showPwWhatsapp');
    if (pwToggle) {
        pwToggle.addEventListener('change', function() {
            const input = document.getElementById('api_token_input');
            if (input) input.type = this.checked ? 'text' : 'password';
        });
    }

    // Refresh inside template modal
    document.getElementById('btnRefreshModalTpl')?.addEventListener('click', fetchMetaTemplates);

    // Search filter inside template modal
    document.getElementById('tplSearchFilter')?.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.tpl-row').forEach(row => {
            const name = row.getAttribute('data-name') || '';
            row.style.display = name.includes(q) ? '' : 'none';
        });
    });

    // Universal Test Button runner
    const btnRunUniversalTest = document.getElementById('btnRunUniversalTest');
    btnRunUniversalTest?.addEventListener('click', function() {
        const phone = document.getElementById('universalTestPhone')?.value.replace(/\D/g, '');
        const resEl = document.getElementById('universalTestResult');
        if (!phone || phone.length < 10) {
            alert('Please enter a valid phone number with country code (e.g. 919876543210).');
            return;
        }

        btnRunUniversalTest.disabled = true;
        btnRunUniversalTest.innerText = 'Sending via Meta API...';
        resEl.className = 'alert alert-info py-2 small';
        resEl.innerText = 'Contacting Meta Graph API...';
        resEl.classList.remove('d-none');

        let url = '';
        if (currentTestType === 'order_confirm') {
            const tplName = document.getElementById('orderConfirmTplInput')?.value.trim() || '';
            url = 'ajax_log_whatsapp.php?test_order_confirm=1&number=' + phone + '&template_name=' + encodeURIComponent(tplName);
        } else if (currentTestType === 'admin_alert') {
            const tplName = document.getElementById('adminTplInput')?.value.trim() || '';
            url = 'ajax_log_whatsapp.php?test_admin=1&number=' + phone + '&admin_template_name=' + encodeURIComponent(tplName);
        } else {
            const tplName = document.getElementById('statusTplInput')?.value.trim() || '';
            url = 'ajax_log_whatsapp.php?test=1&number=' + phone + '&template_name=' + encodeURIComponent(tplName);
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                btnRunUniversalTest.disabled = false;
                btnRunUniversalTest.innerText = 'Send Test Message';

                if (data.success) {
                    const msgId = data.message_id ? `<div class="mt-1 font-monospace small">Message ID: ${data.message_id}</div>` : '';
                    if (data.same_number_warning) {
                        resEl.className = 'alert alert-warning py-2 small';
                        resEl.innerHTML = `⚠️ <strong>Accepted by Meta, but recipient is same as sender (+${data.recipient_number})!</strong> WhatsApp blocks self-messaging. Test with a different phone number.${msgId}`;
                    } else {
                        resEl.className = 'alert alert-success py-2 small';
                        resEl.innerHTML = `✅ <strong>Success!</strong> Meta accepted the message for delivery.${msgId}`;
                    }
                } else {
                    resEl.className = 'alert alert-danger py-2 small';
                    resEl.innerHTML = `❌ <strong>Failed:</strong> ${data.error || 'Meta API rejected message'}`;
                }
            })
            .catch(err => {
                btnRunUniversalTest.disabled = false;
                btnRunUniversalTest.innerText = 'Send Test Message';
                resEl.className = 'alert alert-danger py-2 small';
                resEl.innerText = 'Network error: ' + err.message;
            });
    });

    // Quick Test Admin Alert Handler
    const btnQuickTestAdmin = document.getElementById('btnQuickTestAdmin');
    const adminTestResult   = document.getElementById('adminTestResult');

    btnQuickTestAdmin?.addEventListener('click', function() {
        const adminPhoneInput = document.querySelector('input[name="admin_whatsapp_number"]') || document.querySelector('.phone-hidden-final');
        let rawNumber = adminPhoneInput ? adminPhoneInput.value : '';
        
        if (!rawNumber || rawNumber.replace(/\D/g, '').length < 10) {
            alert('Please enter a valid Admin WhatsApp Number (at least 10 digits) before testing.');
            return;
        }

        btnQuickTestAdmin.disabled = true;
        btnQuickTestAdmin.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending Test Alert...';
        const adminTpl = document.getElementById('adminTplInput')?.value.trim() || '';
        
        fetch('ajax_log_whatsapp.php?test_admin=1&number=' + encodeURIComponent(rawNumber) + '&admin_template_name=' + encodeURIComponent(adminTpl))
            .then(res => res.json())
            .then(data => {
                btnQuickTestAdmin.disabled = false;
                btnQuickTestAdmin.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number';

                if (data.success) {
                    const msgId = data.message_id ? `<div class="mt-1 font-monospace text-muted" style="font-size:0.75rem;">Message ID: ${data.message_id}</div>` : '';
                    if (data.same_number_warning) {
                        adminTestResult.className = 'alert alert-warning py-3 small mt-2 border-warning shadow-sm';
                        adminTestResult.innerHTML = `
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle text-warning fs-5 me-2 mt-1"></i>
                                <div>
                                    <strong class="text-dark">Meta API ने Message Accept किया, लेकिन Delivery Self-Number पर Block है!</strong><br>
                                    <span class="text-muted">आपकी WhatsApp Business API का Sender Number और Admin Alert Number दोनों एक ही हैं (<code>+${data.recipient_number}</code>)।</span>
                                    <div class="mt-2 p-2 bg-white rounded border border-warning text-dark">
                                        <strong>⚠️ WhatsApp Rule:</strong> Meta WhatsApp Cloud API एक Business Account से <u>उसी के अपने नंबर पर</u> मैसेज डिलीवर नहीं करता (Self-Messaging Blocked)।<br>
                                        <strong>💡 Solution:</strong> ऊपर <strong>Admin WhatsApp Number</strong> फ़ील्ड में अपना कोई <strong>दूसरा / पर्सनल WhatsApp नंबर</strong> (जैसे पर्सनल SIM या कोई अन्य मोबाइल नंबर) डालें और दोबारा टेस्ट करें। वहां तुरंत मैसेज प्राप्त होगा!
                                    </div>
                                    ${msgId}
                                </div>
                            </div>
                        `;
                    } else if (data.delivery_type === 'template' || data.bypasses_24h) {
                        adminTestResult.className = 'alert alert-success py-2 small mt-2';
                        adminTestResult.innerHTML = `✅ <strong>Success! Delivered via Meta Template (<code>${data.template_name || 'Approved Template'}</code>)</strong><br>` +
                            `<span class="text-success fw-bold"><i class="fas fa-shield-alt me-1"></i> 24/7 Guaranteed:</span> कल या 24 घंटे बाद भी नया ऑर्डर आने पर बिना किसी "Hi" के ऑटोमैटिक अलर्ट आएगा।${msgId}`;
                        // If input was empty and auto-discovery found the template, populate input
                        if (data.template_name && document.getElementById('adminTplInput') && !document.getElementById('adminTplInput').value) {
                            document.getElementById('adminTplInput').value = data.template_name;
                        }
                    } else {
                        adminTestResult.className = 'alert alert-warning py-2 small mt-2';
                        adminTestResult.innerHTML = `⚠️ <strong>Delivered as Direct Text:</strong> अभी 24 घंटे का सेशन खुला है इसलिए मिल गया, लेकिन कल 24 घंटे बाद नए ऑर्डर पर रुक सकता है। कृपया ऊपर <strong>Admin Meta Template Name</strong> में Approved Template चुनें।${msgId}`;
                    }
                    adminTestResult.classList.remove('d-none');
                } else {
                    adminTestResult.className = 'alert alert-danger py-2 small mt-2';
                    adminTestResult.innerHTML = `❌ <strong>Failed:</strong> ${data.error || 'Meta API error'}`;
                    adminTestResult.classList.remove('d-none');
                }
            })
            .catch(err => {
                btnQuickTestAdmin.disabled = false;
                btnQuickTestAdmin.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send Test Admin Alert to this number';
                adminTestResult.className = 'alert alert-danger py-2 small mt-2';
                adminTestResult.innerText = 'Network error: ' + err.message;
                adminTestResult.classList.remove('d-none');
            });
    });
});
</script>
</div>
<?php require_once 'admin_footer.php'; ?>
