<?php
include 'admin_header.php';

// Handle global settings updates
// Handle global settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    
    // 1. General Settings Block
    if (isset($_POST['general_settings_update'])) {
        if (isset($_POST['timezone'])) {
            $timezone = $conn->real_escape_string($_POST['timezone']);
            $conn->query("UPDATE settings SET setting_value='$timezone' WHERE setting_key='timezone'");
        }
        
        if (isset($_POST['currency'])) {
            $currency = $conn->real_escape_string($_POST['currency']);
            $conn->query("UPDATE settings SET setting_value='$currency' WHERE setting_key='currency_symbol'");
        }
        
        if (isset($_POST['admin_email'])) {
            $admin_email = $conn->real_escape_string($_POST['admin_email']);
            $conn->query("UPDATE settings SET setting_value='$admin_email' WHERE setting_key='admin_email'");
        }
        
        // Logo Upload Logic
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['site_logo']['tmp_name'];
            $file_name = $_FILES['site_logo']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $new_name = 'logo_' . time() . '.' . $ext;
                $upload_dir = '../assets/images/';
                if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('header_logo_image', '$new_name') ON DUPLICATE KEY UPDATE setting_value='$new_name'");
                }
            }
        }

        // Footer Logo Upload Logic
        if (isset($_FILES['footer_logo']) && $_FILES['footer_logo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['footer_logo']['tmp_name'];
            $file_name = $_FILES['footer_logo']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $new_name = 'footer_logo_' . time() . '.' . $ext;
                $upload_dir = '../assets/images/';
                if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('footer_logo_image', '$new_name') ON DUPLICATE KEY UPDATE setting_value='$new_name'");
                }
            }
        }

        // Logo Height Logic
        if (isset($_POST['logo_height'])) {
            $logo_height = $conn->real_escape_string($_POST['logo_height']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('header_logo_height', '$logo_height') ON DUPLICATE KEY UPDATE setting_value='$logo_height'");
        }

        if (isset($_POST['footer_logo_height'])) {
            $footer_logo_height = $conn->real_escape_string($_POST['footer_logo_height']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('footer_logo_height', '$footer_logo_height') ON DUPLICATE KEY UPDATE setting_value='$footer_logo_height'");
        }

        // Logo Display Toggles
        $show_header_logo = isset($_POST['show_header_logo']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('show_header_logo', '$show_header_logo') ON DUPLICATE KEY UPDATE setting_value='$show_header_logo'");

        $show_footer_logo = isset($_POST['show_footer_logo']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('show_footer_logo', '$show_footer_logo') ON DUPLICATE KEY UPDATE setting_value='$show_footer_logo'");
        
        // Product Page Settings
        if (isset($_POST['product_shipping_text'])) {
            $val = $conn->real_escape_string($_POST['product_shipping_text']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('product_shipping_text', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['product_return_text'])) {
            $val = $conn->real_escape_string($_POST['product_return_text']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('product_return_text', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['product_warranty_text'])) {
            $val = $conn->real_escape_string($_POST['product_warranty_text']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('product_warranty_text', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }

        // Footer Titles Settings
        if (isset($_POST['footer_col2_title'])) {
            $val = $conn->real_escape_string($_POST['footer_col2_title']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('footer_col2_title', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['footer_col3_title'])) {
            $val = $conn->real_escape_string($_POST['footer_col3_title']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('footer_col3_title', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['footer_col4_title'])) {
            $val = $conn->real_escape_string($_POST['footer_col4_title']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('footer_col4_title', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }

        // Header Search Setting
        $enable_header_search = isset($_POST['enable_header_search']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('enable_header_search', '$enable_header_search') ON DUPLICATE KEY UPDATE setting_value='$enable_header_search'");

        // Automatic Text Contrast Setting
        $auto_text_contrast = isset($_POST['auto_text_contrast']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_text_contrast', '$auto_text_contrast') ON DUPLICATE KEY UPDATE setting_value='$auto_text_contrast'");

        // Maintenance Mode
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '$maintenance_mode') ON DUPLICATE KEY UPDATE setting_value='$maintenance_mode'");

        // Email Notifications Toggle
        $enable_email_notifications = isset($_POST['enable_email_notifications']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('enable_email_notifications', '$enable_email_notifications') ON DUPLICATE KEY UPDATE setting_value='$enable_email_notifications'");

        // SMTP Settings
        if (isset($_POST['smtp_provider'])) {
            $smtp_provider = $conn->real_escape_string($_POST['smtp_provider']);
            $smtp_host = $conn->real_escape_string($_POST['smtp_host'] ?? '');
            $smtp_port = $conn->real_escape_string($_POST['smtp_port'] ?? '');
            $smtp_username = $conn->real_escape_string($_POST['smtp_username'] ?? '');
            $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption'] ?? 'tls');
            $smtp_sender_email = $conn->real_escape_string($_POST['smtp_sender_email'] ?? '');
            $smtp_sender_name = $conn->real_escape_string($_POST['smtp_sender_name'] ?? '');
            
            // Encrypt the password if provided and not empty placeholder
            $smtp_password = $_POST['smtp_password'] ?? '';
            $encrypted_pass = '';
            if (!empty($smtp_password) && $smtp_password !== '********') {
                $encryption_key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_fallback_secret_key_123!';
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                $enc = openssl_encrypt($smtp_password, 'aes-256-cbc', $encryption_key, 0, $iv);
                $encrypted_pass = base64_encode($enc . '::' . $iv);
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_password', '$encrypted_pass') ON DUPLICATE KEY UPDATE setting_value='$encrypted_pass'");
            }

            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_provider', '$smtp_provider') ON DUPLICATE KEY UPDATE setting_value='$smtp_provider'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_host', '$smtp_host') ON DUPLICATE KEY UPDATE setting_value='$smtp_host'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_port', '$smtp_port') ON DUPLICATE KEY UPDATE setting_value='$smtp_port'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_username', '$smtp_username') ON DUPLICATE KEY UPDATE setting_value='$smtp_username'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_encryption', '$smtp_encryption') ON DUPLICATE KEY UPDATE setting_value='$smtp_encryption'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_sender_email', '$smtp_sender_email') ON DUPLICATE KEY UPDATE setting_value='$smtp_sender_email'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_sender_name', '$smtp_sender_name') ON DUPLICATE KEY UPDATE setting_value='$smtp_sender_name'");
        }
    }

    // 2. Payments Block
    if (isset($_POST['payments_settings_update'])) {
        // COD Settings
        if (isset($_POST['cod_enabled'])) {
            $cod_enabled = $conn->real_escape_string($_POST['cod_enabled']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_enabled', '$cod_enabled') ON DUPLICATE KEY UPDATE setting_value='$cod_enabled'");
        }

        // ── COD Advance Payment Settings ────────────────────────────
        $cod_advance_enabled = isset($_POST['cod_advance_enabled']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_advance_enabled', '$cod_advance_enabled') ON DUPLICATE KEY UPDATE setting_value='$cod_advance_enabled'");

        if (isset($_POST['cod_advance_percentage'])) {
            $cod_adv_pct = max(1, min(99, (int)$_POST['cod_advance_percentage']));
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_advance_percentage', '$cod_adv_pct') ON DUPLICATE KEY UPDATE setting_value='$cod_adv_pct'");
        }
        if (isset($_POST['cod_advance_min_order'])) {
            $cod_adv_min = max(0, (float)$_POST['cod_advance_min_order']);
            $cod_adv_min = $conn->real_escape_string($cod_adv_min);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_advance_min_order', '$cod_adv_min') ON DUPLICATE KEY UPDATE setting_value='$cod_adv_min'");
        }
        // ────────────────────────────────────────────────────────────

        // ── COD Advanced Charges Settings ────────────────────────
        if (isset($_POST['cod_default_charge'])) {
            $val = max(0, (float)$_POST['cod_default_charge']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_default_charge', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['cod_charge_mode'])) {
            $val = in_array($_POST['cod_charge_mode'], ['highest', 'sum']) ? $_POST['cod_charge_mode'] : 'highest';
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_charge_mode', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['cod_free_threshold'])) {
            $val = max(0, (float)$_POST['cod_free_threshold']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cod_free_threshold', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        // ────────────────────────────────────────────────────────────
        
        // PhonePe Settings
        if (isset($_POST['phonepe_enabled'])) {
            $phonepe_enabled = $conn->real_escape_string($_POST['phonepe_enabled']);
            $phonepe_mode = $conn->real_escape_string($_POST['phonepe_mode']);
            $phonepe_merchant_id = $conn->real_escape_string($_POST['phonepe_merchant_id']);
            $phonepe_salt_key = $conn->real_escape_string($_POST['phonepe_salt_key']);
            $phonepe_salt_index = $conn->real_escape_string($_POST['phonepe_salt_index']);
            
            $conn->query("UPDATE settings SET setting_value='$phonepe_enabled' WHERE setting_key='phonepe_enabled'");
            $conn->query("UPDATE settings SET setting_value='$phonepe_mode' WHERE setting_key='phonepe_mode'");
            $conn->query("UPDATE settings SET setting_value='$phonepe_merchant_id' WHERE setting_key='phonepe_merchant_id'");
            $conn->query("UPDATE settings SET setting_value='$phonepe_salt_key' WHERE setting_key='phonepe_salt_key'");
            $conn->query("UPDATE settings SET setting_value='$phonepe_salt_index' WHERE setting_key='phonepe_salt_index'");
        }
    }

    // 3. Social Login Settings (Already has marker)
    if (isset($_POST['google_login_update'])) {
        $google_login_enabled = isset($_POST['google_login_enabled']) ? '1' : '0';
        $google_one_tap_enabled = isset($_POST['google_one_tap_enabled']) ? '1' : '0';
        $google_client_id = $conn->real_escape_string($_POST['google_client_id'] ?? '');
        $google_client_secret = $conn->real_escape_string($_POST['google_client_secret'] ?? '');

        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('google_login_enabled', '$google_login_enabled') ON DUPLICATE KEY UPDATE setting_value='$google_login_enabled'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('google_one_tap_enabled', '$google_one_tap_enabled') ON DUPLICATE KEY UPDATE setting_value='$google_one_tap_enabled'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('google_client_id', '$google_client_id') ON DUPLICATE KEY UPDATE setting_value='$google_client_id'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('google_client_secret', '$google_client_secret') ON DUPLICATE KEY UPDATE setting_value='$google_client_secret'");
    }

    // 4. Hero Banners Block
    if (isset($_POST['banners_settings_update'])) {
        $hero_pages = ['home', 'about', 'contact', 'support', 'faq', 'policy', 'category', 'product'];
        foreach ($hero_pages as $page) {
            $setting_key = "hero_banner_{$page}";
            
            // Handle Delete
            if (isset($_POST["delete_{$setting_key}"]) && $_POST["delete_{$setting_key}"] == '1') {
                // Get old image
                $old_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$setting_key'");
                if ($old_q && $old = $old_q->fetch_assoc()) {
                    $old_img = $old['setting_value'];
                    if ($old_img && file_exists('../assets/images/' . $old_img)) {
                        unlink('../assets/images/' . $old_img);
                    }
                }
                $conn->query("DELETE FROM settings WHERE setting_key='$setting_key'");
            }
            
            // Handle Upload
            if (isset($_FILES[$setting_key]) && $_FILES[$setting_key]['error'] === UPLOAD_ERR_OK) {
                $tmp_name  = $_FILES[$setting_key]['tmp_name'];
                $file_name = $_FILES[$setting_key]['name'];
                $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($ext, $allowed)) {
                    // Get old image to replace
                    $old_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$setting_key'");
                    if ($old_q && $old = $old_q->fetch_assoc()) {
                        $old_img = $old['setting_value'];
                        if ($old_img && file_exists('../assets/images/' . $old_img)) {
                            unlink('../assets/images/' . $old_img);
                        }
                    }

                    $new_name = "hero_{$page}_" . time() . '.' . $ext;
                    if (move_uploaded_file($tmp_name, '../assets/images/' . $new_name)) {
                        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$setting_key', '$new_name') ON DUPLICATE KEY UPDATE setting_value='$new_name'");
                    }
                }
            }
        }
    }

    // 5. Frontend Content Settings
    if (isset($_POST['frontend_settings_update'])) {
        $testimonial_enabled = isset($_POST['testimonial_section_enabled']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('testimonial_section_enabled', '$testimonial_enabled') ON DUPLICATE KEY UPDATE setting_value='$testimonial_enabled'");
        
        if (isset($_POST['testimonial_section_title'])) {
            $val = $conn->real_escape_string($_POST['testimonial_section_title']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('testimonial_section_title', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        if (isset($_POST['testimonial_section_subtitle'])) {
            $val = $conn->real_escape_string($_POST['testimonial_section_subtitle']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('testimonial_section_subtitle', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        if (isset($_POST['testimonial_show_count'])) {
            $val = intval($_POST['testimonial_show_count']);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('testimonial_show_count', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        $_SESSION['flash_success'] = "Settings updated successfully.";
        header("Location: manage_settings.php");
        exit;
    }
}

// Flash messages
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle Menu CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add_menu') {
        $name = $conn->real_escape_string($_POST['name']);
        $url = $conn->real_escape_string($_POST['url']);
        $location = $conn->real_escape_string($_POST['menu_location']);
        $order = intval($_POST['order_index']);
        
        $parent = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : "NULL";
        
        $conn->query("INSERT INTO menus (name, url, menu_location, parent_id, order_index) VALUES ('$name', '$url', '$location', $parent, $order)");
        $success = "Menu item added successfully.";
    } elseif ($action === 'edit_menu') {
        $id = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $url = $conn->real_escape_string($_POST['url']);
        $location = $conn->real_escape_string($_POST['menu_location']);
        $order = intval($_POST['order_index']);
        
        $conn->query("UPDATE menus SET name='$name', url='$url', menu_location='$location', order_index=$order WHERE id=$id");
        $success = "Menu item updated successfully.";
    } elseif ($action === 'delete_menu') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM menus WHERE id=$id");
        $success = "Menu item deleted successfully.";
    }
}

// Fetch current settings
$set_q = $conn->query("SELECT * FROM settings");
$current_settings = [];
while($r = $set_q->fetch_assoc()) {
    $current_settings[$r['setting_key']] = $r['setting_value'];
}

// Fetch Top Level Menus
$top_menus = $conn->query("SELECT * FROM menus WHERE parent_id IS NULL ORDER BY order_index ASC");

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">System Configuration</h4>
</div>

<?php if(isset($success)): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<!-- Tabs Nav -->
<ul class="nav nav-tabs mb-4 border-bottom-0" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'general' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=general">General</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'payment' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=payment">Payments</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'shipping' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=shipping">Shipping Logic</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'menus' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=menus">User Menus</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'banners' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=banners">Page Hero Banners</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'social_login' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=social_login">Social Login</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $active_tab == 'frontend' ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted'; ?>" href="?tab=frontend">Frontend Content</a>
  </li>
</ul>

<div class="tab-content" id="settingsTabContent">

    <!-- General Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'general' ? 'show active' : ''; ?>" id="tab-general" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="max-width: 600px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="fw-bold m-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Global Properties</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="manage_settings.php?tab=general" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="general_settings_update" value="1">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Site Logo (Header)</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <?php 
                            $logo = $current_settings['header_logo_image'] ?? 'logo.jpg';
                            ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($logo); ?>" alt="Current Logo" class="img-thumbnail" style="max-height: 80px;">
                            <div class="flex-grow-1">
                                <input type="file" name="site_logo" class="form-control" accept="image/*">
                                <small class="text-muted">Recommended: PNG or transparent background.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="show_header_logo" id="showHeaderLogo" <?php echo (!isset($current_settings['show_header_logo']) || $current_settings['show_header_logo'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2" for="showHeaderLogo">Enable Site Logo display in Header</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Footer Logo</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <?php 
                            $f_logo = $current_settings['footer_logo_image'] ?? 'logo.jpg';
                            ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($f_logo); ?>" alt="Current Footer Logo" class="img-thumbnail" style="max-height: 80px;">
                            <div class="flex-grow-1">
                                <input type="file" name="footer_logo" class="form-control" accept="image/*">
                                <small class="text-muted">Recommended: PNG or transparent background.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="show_footer_logo" id="showFooterLogo" <?php echo (!isset($current_settings['show_footer_logo']) || $current_settings['show_footer_logo'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2" for="showFooterLogo">Enable Footer Logo display</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Header Logo Height (px)</label>
                        <div class="input-group" style="max-width: 150px;">
                            <input type="number" name="logo_height" class="form-control" value="<?php echo htmlspecialchars($current_settings['header_logo_height'] ?? '40'); ?>" min="20" max="200">
                            <span class="input-group-text">px</span>
                        </div>
                        <small class="text-muted">Default: 40px.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Footer Logo Height (px)</label>
                        <div class="input-group" style="max-width: 150px;">
                            <input type="number" name="footer_logo_height" class="form-control" value="<?php echo htmlspecialchars($current_settings['footer_logo_height'] ?? '45'); ?>" min="20" max="200">
                            <span class="input-group-text">px</span>
                        </div>
                        <small class="text-muted">Default: 45px.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">System Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php 
                            $timezones = DateTimeZone::listIdentifiers();
                            foreach($timezones as $tz) {
                                $selected = ($current_settings['timezone'] == $tz) ? 'selected' : '';
                                echo "<option value=\"$tz\" $selected>$tz</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Store Currency Symbol</label>
                        <input type="text" name="currency" class="form-control" value="<?php echo htmlspecialchars($current_settings['currency_symbol'] ?? '₹'); ?>" required>
                        <small class="text-muted">E.g., $, ₹, €, £</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Admin Notification Email</label>
                        <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? 'admin@store.com'); ?>" required>
                        <small class="text-muted">New order notifications will be sent here.</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="enable_email_notifications" id="enableEmails" <?php echo (isset($current_settings['enable_email_notifications']) && $current_settings['enable_email_notifications'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6" for="enableEmails">Enable Email Notifications</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="enable_header_search" id="enableSearch" <?php echo (isset($current_settings['enable_header_search']) && $current_settings['enable_header_search'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6" for="enableSearch">Enable Header Search Bar</label>
                        </div>
                        <small class="text-muted text-start d-block mt-1 ps-5">Displays a search option to the left of the cart icon.</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="auto_text_contrast" id="autoTextContrast" <?php echo (isset($current_settings['auto_text_contrast']) && $current_settings['auto_text_contrast'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="autoTextContrast">Automatic Text Contrast</label>
                        </div>
                        <small class="text-muted text-start d-block mt-1 ps-5">Automatically adjust text color (Light/Dark) based on section background brightness.</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="maintenance_mode" id="maintenanceMode" <?php echo (isset($current_settings['maintenance_mode']) && $current_settings['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6" for="maintenanceMode">
                                <span class="text-danger fw-bold"><i class="fas fa-hard-hat me-1"></i>Maintenance Mode</span>
                            </label>
                        </div>
                        <small class="text-danger text-start d-block mt-1 ps-5"><i class="fas fa-exclamation-triangle me-1"></i>When ON, visitors will see a maintenance page. Admins can still access the site.</small>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-secondary">Product Page Text Config</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Shipping Info Text <i class="fas fa-truck ms-1 text-muted"></i></label>
                        <input type="text" name="product_shipping_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['product_shipping_text'] ?? 'Free Shipping on orders over ₹1000'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Return Policy Text <i class="fas fa-undo ms-1 text-muted"></i></label>
                        <input type="text" name="product_return_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['product_return_text'] ?? '7-Day Return Policy'); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Warranty Text <i class="fas fa-shield-alt ms-1 text-muted"></i></label>
                        <input type="text" name="product_warranty_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['product_warranty_text'] ?? '1 Year Warranty Included'); ?>" required>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-secondary">Footer Section Titles</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Footer Column 2 Title</label>
                        <input type="text" name="footer_col2_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['footer_col2_title'] ?? 'For Him'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Footer Column 3 Title</label>
                        <input type="text" name="footer_col3_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['footer_col3_title'] ?? 'Support'); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Footer Column 4 Title</label>
                        <input type="text" name="footer_col4_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['footer_col4_title'] ?? 'Send Me'); ?>" required>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-secondary">SMTP / Email Configuration</h6>
                        <button type="button" id="testSmtpBtn" class="btn btn-outline-info btn-sm"><i class="fas fa-paper-plane me-1"></i>Test SMTP Connection</button>
                    </div>
                    <div class="alert alert-info py-2" style="font-size:0.85rem;">
                        <i class="fas fa-info-circle me-1"></i> If these settings are entirely blank or Provider is Environmental, the system will seamlessly fallback to `.env` config.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">SMTP Provider</label>
                        <select name="smtp_provider" id="smtp_provider" class="form-select text-primary fw-bold" onchange="autoFillSmtp()">
                            <option value="env" <?php echo (!isset($current_settings['smtp_provider']) || $current_settings['smtp_provider'] == 'env') ? 'selected' : ''; ?>>Fallback to .env File</option>
                            <option value="gmail" <?php echo (isset($current_settings['smtp_provider']) && $current_settings['smtp_provider'] == 'gmail') ? 'selected' : ''; ?>>Gmail SMTP (App Passwords)</option>
                            <option value="hostinger" <?php echo (isset($current_settings['smtp_provider']) && $current_settings['smtp_provider'] == 'hostinger') ? 'selected' : ''; ?>>Hostinger SMTP</option>
                            <option value="custom" <?php echo (isset($current_settings['smtp_provider']) && $current_settings['smtp_provider'] == 'custom') ? 'selected' : ''; ?>>Custom SMTP</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">SMTP Host</label>
                            <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?php echo htmlspecialchars($current_settings['smtp_host'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">SMTP Port</label>
                            <input type="text" name="smtp_port" id="smtp_port" class="form-control" value="<?php echo htmlspecialchars($current_settings['smtp_port'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Encryption Type</label>
                            <select name="smtp_encryption" id="smtp_encryption" class="form-select">
                                <option value="tls" <?php echo (isset($current_settings['smtp_encryption']) && $current_settings['smtp_encryption'] == 'tls') ? 'selected' : ''; ?>>TLS (StartTLS)</option>
                                <option value="ssl" <?php echo (isset($current_settings['smtp_encryption']) && $current_settings['smtp_encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Username / Email</label>
                            <input type="text" name="smtp_username" id="smtp_username" class="form-control" value="<?php echo htmlspecialchars($current_settings['smtp_username'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Password</label>
                            <input type="password" name="smtp_password" id="smtp_password" class="form-control" placeholder="<?php echo !empty($current_settings['smtp_password']) ? '********' : 'Enter new password to update'; ?>">
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwSmtp">
                                <label class="form-check-label small text-muted" for="showPwSmtp">Show password</label>
                            </div>
                            <small class="text-muted d-block mt-1">Stored securely via AES-256 encryption.</small>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sender Email (From)</label>
                            <input type="text" name="smtp_sender_email" id="smtp_sender_email" class="form-control" value="<?php echo htmlspecialchars($current_settings['smtp_sender_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sender Name (From Name)</label>
                            <input type="text" name="smtp_sender_name" id="smtp_sender_name" class="form-control" value="<?php echo htmlspecialchars($current_settings['smtp_sender_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <script>
                    function autoFillSmtp() {
                        const provider = document.getElementById('smtp_provider').value;
                        const host = document.getElementById('smtp_host');
                        const port = document.getElementById('smtp_port');
                        const enc = document.getElementById('smtp_encryption');
                        
                        if (provider === 'gmail') {
                            host.value = 'smtp.gmail.com';
                            port.value = '587';
                            enc.value = 'tls';
                        } else if (provider === 'hostinger') {
                            host.value = 'smtp.hostinger.com';
                            port.value = '465';
                            enc.value = 'ssl';
                        }
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        const testBtn = document.getElementById('testSmtpBtn');
                        if (testBtn) {
                            testBtn.addEventListener('click', function() {
                                const formData = new FormData(testBtn.closest('form'));
                                // Exclude big actions
                                formData.delete('action');
                                formData.append('action', 'test_smtp');
                                
                                testBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing...';
                                testBtn.disabled = true;
                                
                                fetch('test_smtp_connection.php', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    body: formData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    testBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Test SMTP Connection';
                                    testBtn.disabled = false;
                                    alert(data.success ? 'Success: ' + data.message : 'Error: ' + data.message);
                                })
                                .catch(err => {
                                    testBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Test SMTP Connection';
                                    testBtn.disabled = false;
                                    alert('Request failed. Check console.');
                                    console.error(err);
                                });
                            });
                        }
                    });
                    </script>

                    <button type="submit" class="btn btn-primary btn-custom w-100">Save General Settings</button>
                </form>
            </div>
        </div>
    </div> <!-- End General Tab -->
    
    <!-- Payments Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'payment' ? 'show active' : ''; ?>" id="tab-payment" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="max-width: 600px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="fw-bold m-0"><i class="fas fa-wallet me-2 text-primary"></i>Payment Settings</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="manage_settings.php?tab=payment">
    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="payments_settings_update" value="1">
                    <h6 class="fw-bold mb-3 text-secondary">Offline Payment Settings</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cash On Delivery (COD) Status</label>
                        <select name="cod_enabled" id="cod_enabled_select" class="form-select" onchange="toggleCodAdvanceSection()">
                            <option value="1" <?php echo (isset($current_settings['cod_enabled']) && $current_settings['cod_enabled'] == '1') ? 'selected' : ''; ?>>Enabled</option>
                            <option value="0" <?php echo (!isset($current_settings['cod_enabled']) || $current_settings['cod_enabled'] == '0') ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                        <small class="text-muted">If enabled, COD will be available for customers at checkout.</small>
                    </div>

                    <?php
                    /* ── COD Advance Sub-section ───────────────────────────────── */
                    $cod_adv_enabled = ($current_settings['cod_advance_enabled'] ?? '0') === '1';
                    $cod_adv_pct     = $current_settings['cod_advance_percentage'] ?? '30';
                    $cod_adv_min     = $current_settings['cod_advance_min_order'] ?? '0';
                    $cod_is_on       = ($current_settings['cod_enabled'] ?? '0') === '1';
                    ?>
                    <div id="cod_advance_section" class="border rounded-3 p-3 mb-3 bg-light" style="<?php echo $cod_is_on ? '' : 'display:none;'; ?>">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-percentage me-2"></i>COD Advance Payment (Partial COD)</h6>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            When enabled, customers must pay a percentage online via PhonePe before the COD order is confirmed.
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="cod_advance_enabled" id="cod_advance_enabled"
                                    <?php echo $cod_adv_enabled ? 'checked' : ''; ?>
                                    onchange="toggleAdvanceFields()">
                                <label class="form-check-label ms-2 fs-6 fw-bold" for="cod_advance_enabled">
                                    Enable COD Advance Payment
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">Customer pays advance online; rest is paid at delivery.</small>
                        </div>

                        <div id="cod_advance_fields" style="<?php echo $cod_adv_enabled ? '' : 'display:none;'; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Advance Percentage <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="cod_advance_percentage" class="form-control"
                                            value="<?php echo htmlspecialchars($cod_adv_pct); ?>"
                                            min="1" max="99" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">% of order total charged as advance online.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Minimum Order Value</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo htmlspecialchars($global_currency); ?></span>
                                        <input type="number" name="cod_advance_min_order" class="form-control"
                                            value="<?php echo htmlspecialchars($cod_adv_min); ?>"
                                            min="0" step="0.01">
                                    </div>
                                    <small class="text-muted">0 = apply to all orders. Otherwise only applies when cart &ge; this amount.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    function toggleCodAdvanceSection() {
                        var codSelect = document.getElementById('cod_enabled_select');
                        var advSection = document.getElementById('cod_advance_section');
                        if (codSelect && advSection) {
                            advSection.style.display = (codSelect.value === '1') ? '' : 'none';
                        }
                    }
                    function toggleAdvanceFields() {
                        var toggle = document.getElementById('cod_advance_enabled');
                        var fields = document.getElementById('cod_advance_fields');
                        if (toggle && fields) {
                            fields.style.display = toggle.checked ? '' : 'none';
                        }
                    }
                    </script>

                    <!-- ── COD Charges Configuration ──────────────────────── -->
                    <div id="cod_charges_section" class="border rounded-3 p-3 mb-3" style="background: linear-gradient(135deg, #f0fff4 0%, #e8f5e9 100%); <?php echo $cod_is_on ? '' : 'display:none;'; ?>">
                        <h6 class="fw-bold mb-3 text-success"><i class="fas fa-money-bill-wave me-2"></i>COD Charges Configuration</h6>
                        <div class="alert alert-success py-2 small mb-3 border-0 bg-success bg-opacity-10">
                            <i class="fas fa-info-circle me-1"></i>
                            Set product-wise COD charges and free COD thresholds. Each product can have its own charge set in Product Settings.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Default COD Charge <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo htmlspecialchars($global_currency); ?></span>
                                    <input type="number" name="cod_default_charge" class="form-control"
                                        value="<?php echo htmlspecialchars($current_settings['cod_default_charge'] ?? '0'); ?>"
                                        min="0" step="0.01">
                                </div>
                                <small class="text-muted">Applied when a product doesn't have its own COD charge set.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Free COD Threshold</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo htmlspecialchars($global_currency); ?></span>
                                    <input type="number" name="cod_free_threshold" class="form-control"
                                        value="<?php echo htmlspecialchars($current_settings['cod_free_threshold'] ?? '0'); ?>"
                                        min="0" step="0.01">
                                </div>
                                <small class="text-muted">If order total &ge; this amount, COD charge = &curren;0. Set 0 to disable.</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Cart Charge Mode</label>
                            <?php $charge_mode = $current_settings['cod_charge_mode'] ?? 'highest'; ?>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cod_charge_mode" id="cod_mode_highest" value="highest" <?php echo $charge_mode === 'highest' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="cod_mode_highest">
                                        <i class="fas fa-arrow-up me-1 text-primary"></i>Highest
                                    </label>
                                    <div class="form-text small">Take the highest COD charge among cart products.</div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cod_charge_mode" id="cod_mode_sum" value="sum" <?php echo $charge_mode === 'sum' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="cod_mode_sum">
                                        <i class="fas fa-plus me-1 text-warning"></i>Sum
                                    </label>
                                    <div class="form-text small">Sum up COD charges of all cart products.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                            <small class="text-muted"><i class="fas fa-ban me-1 text-danger"></i>Manage COD restrictions per user</small>
                            <a href="manage_cod_blacklist.php" class="btn btn-outline-danger btn-sm btn-custom"><i class="fas fa-shield-alt me-1"></i>COD Blacklist Manager</a>
                        </div>
                    </div>

                    <script>
                    // Update toggleCodAdvanceSection to also toggle COD charges section
                    (function() {
                        var origToggle = toggleCodAdvanceSection;
                        toggleCodAdvanceSection = function() {
                            origToggle();
                            var codSelect = document.getElementById('cod_enabled_select');
                            var chargesSection = document.getElementById('cod_charges_section');
                            if (codSelect && chargesSection) {
                                chargesSection.style.display = (codSelect.value === '1') ? '' : 'none';
                            }
                        };
                    })();
                    </script>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-secondary">PhonePe Payment Gateway Settings</h6>
                        <a href="manage_phonepe_transactions.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list-alt me-1"></i>View Transaction Statements</a>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Status</label>
                        <select name="phonepe_enabled" class="form-select">
                            <option value="1" <?php echo (isset($current_settings['phonepe_enabled']) && $current_settings['phonepe_enabled'] == '1') ? 'selected' : ''; ?>>Enabled</option>
                            <option value="0" <?php echo (!isset($current_settings['phonepe_enabled']) || $current_settings['phonepe_enabled'] == '0') ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">PhonePe Mode</label>
                        <select name="phonepe_mode" class="form-select">
                            <option value="sandbox" <?php echo (isset($current_settings['phonepe_mode']) && $current_settings['phonepe_mode'] === 'sandbox') ? 'selected' : ''; ?>>Sandbox / Test</option>
                            <option value="live" <?php echo (isset($current_settings['phonepe_mode']) && $current_settings['phonepe_mode'] === 'live') ? 'selected' : ''; ?>>Live / Production</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Merchant ID</label>
                        <input type="text" name="phonepe_merchant_id" class="form-control" value="<?php echo htmlspecialchars($current_settings['phonepe_merchant_id'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Salt Key</label>
                        <input type="text" name="phonepe_salt_key" class="form-control" value="<?php echo htmlspecialchars($current_settings['phonepe_salt_key'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Salt Index</label>
                        <input type="text" name="phonepe_salt_index" class="form-control" value="<?php echo htmlspecialchars($current_settings['phonepe_salt_index'] ?? '1'); ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-custom w-100">Save Payment Settings</button>
                </form>
            </div>
        </div>
    </div> <!-- End Payment Tab -->

    <!-- Social Login Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'social_login' ? 'show active' : ''; ?>" id="tab-social_login" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="max-width: 600px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="fw-bold m-0"><i class="fab fa-google me-2 text-primary"></i>Google Authentication</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="manage_settings.php?tab=social_login">
    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="google_login_update" value="1">
                    
                    <div class="alert alert-info py-2 small mb-4">
                        <i class="fas fa-info-circle me-2"></i>Configure your OAuth 2.0 Credentials from the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="fw-bold alert-link">Google Cloud Console</a>.
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="google_login_enabled" id="googleLoginEnabled" <?php echo (isset($current_settings['google_login_enabled']) && $current_settings['google_login_enabled'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="googleLoginEnabled">Enable Google Login</label>
                        </div>
                        <small class="text-muted d-block mt-1">Shows "Continue with Google" on Login and Signup pages.</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="google_one_tap_enabled" id="googleOneTapEnabled" <?php echo (isset($current_settings['google_one_tap_enabled']) && $current_settings['google_one_tap_enabled'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="googleOneTapEnabled">Enable Google One Tap</label>
                        </div>
                        <small class="text-muted d-block mt-1">Displays the frictionless Google One Tap popup prompt to visitors.</small>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Google Client ID</label>
                        <input type="text" name="google_client_id" class="form-control" value="<?php echo htmlspecialchars($current_settings['google_client_id'] ?? ''); ?>" placeholder="e.g. 1234567890-abcxyz.apps.googleusercontent.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Google Client Secret</label>
                        <input type="password" name="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($current_settings['google_client_secret'] ?? ''); ?>" placeholder="GOCSPX-xxxxxx">
                    </div>

                    <?php 
                        $site_base = defined('SITE_URL') && SITE_URL !== '' 
                            ? rtrim(SITE_URL, '/') 
                            : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
                        $redirect_uri = $site_base . '/auth/google_callback.php';
                    ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Authorized Redirect URI (Copy to Google Console)</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" id="googleRedirectUri" value="<?php echo htmlspecialchars($redirect_uri); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('googleRedirectUri')"><i class="fas fa-copy"></i> Copy</button>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-custom flex-grow-1">Save Social Login Settings</button>
                        <button type="button" class="btn btn-outline-success btn-custom" id="testGoogleConnBtn"><i class="fas fa-plug me-2"></i>Test Connection</button>
                    </div>
                </form>
            </div>
        </div>
    </div> <!-- End Social Login Tab -->

    <!-- Frontend Content Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'frontend' ? 'show active' : ''; ?>" id="tab-frontend" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="max-width: 600px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="fw-bold m-0"><i class="fas fa-desktop me-2 text-primary"></i>Testimonials Section Control</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="manage_settings.php?tab=frontend">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="frontend_settings_update" value="1">
                    
                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="testimonial_section_enabled" id="testiEnabled" <?php echo (!isset($current_settings['testimonial_section_enabled']) || $current_settings['testimonial_section_enabled'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="testiEnabled">Enable Testimonials Section</label>
                        </div>
                        <small class="text-muted d-block mt-1">Show or hide the testimonials slider on the homepage.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Main Title</label>
                        <input type="text" name="testimonial_section_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['testimonial_section_title'] ?? 'What Our Customers Say'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Subtitle</label>
                        <input type="text" name="testimonial_section_subtitle" class="form-control" value="<?php echo htmlspecialchars($current_settings['testimonial_section_subtitle'] ?? 'Read honest reviews from people who love our products.'); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Display Count</label>
                        <div class="input-group" style="max-width: 150px;">
                            <input type="number" name="testimonial_show_count" class="form-control" value="<?php echo htmlspecialchars($current_settings['testimonial_show_count'] ?? '10'); ?>" min="1" max="50">
                            <span class="input-group-text">Reviews</span>
                        </div>
                        <small class="text-muted">Maximum number of testimonials to show in the slider.</small>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex align-items-center justify-content-between">
                         <div class="text-muted small"><i class="fas fa-info-circle me-1"></i> These settings control the visible slider on the website.</div>
                         <button type="submit" class="btn btn-primary btn-custom px-4">Update Frontend Content</button>
                    </div>
                </form>
            </div>
        </div>
    </div> <!-- End Frontend Tab -->
    
    <!-- Shipping Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'shipping' ? 'show active' : ''; ?>" id="tab-shipping" role="tabpanel">
        <div class="row mt-2">
            <div class="col-md-12">
                <!-- Render modular shipping panel -->
                <?php 
                    include __DIR__ . '/../shipping_module_src/examples/admin_shipping_panel_example.php'; 
                ?>
            </div>
        </div>
    </div> <!-- End Shipping Tab -->

    <!-- Menus Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'menus' ? 'show active' : ''; ?>" id="tab-menus" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fas fa-bars me-2 text-primary"></i>Navigation Menus</h5>
                <button class="btn btn-primary btn-sm btn-custom px-3" data-mdb-toggle="modal" data-mdb-target="#addMenuModal">
                    <i class="fas fa-plus me-1"></i>Add Menu
                </button>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Location</th>
                                <th>Order</th>
                                <th>Name</th>
                                <th>Link URL</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch all menus flat but order by location, parent/child logic simply
                            $query = "
                            SELECT m1.*, m2.name as parent_name 
                            FROM menus m1 
                            LEFT JOIN menus m2 ON m1.parent_id = m2.id
                            ORDER BY 
                                m1.menu_location ASC,
                                COALESCE(m1.parent_id, m1.id) ASC, 
                                m1.parent_id IS NOT NULL ASC,
                                m1.order_index ASC
                            ";
                            $all_menus = $conn->query($query);
                            ?>
                            
                            <?php if($all_menus && $all_menus->num_rows > 0): ?>
                                <?php while($m = $all_menus->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php 
                                            if($m['menu_location'] == 'header') echo '<span class="badge bg-primary">Header</span>';
                                            elseif($m['menu_location'] == 'footer1') echo '<span class="badge bg-secondary">Footer Col 2</span>';
                                            elseif($m['menu_location'] == 'footer2') echo '<span class="badge bg-dark">Footer Col 3</span>';
                                            elseif($m['menu_location'] == 'footer3') echo '<span class="badge bg-danger">Footer Col 4</span>';
                                            elseif($m['menu_location'] == 'both1') echo '<span class="badge bg-info">Header & Col 2</span>';
                                            elseif($m['menu_location'] == 'both2') echo '<span class="badge bg-info">Header & Col 3</span>';
                                            elseif($m['menu_location'] == 'both3') echo '<span class="badge bg-info">Header & Col 4</span>';
                                        ?>
                                    </td>
                                    <td class="text-muted"><?php echo $m['order_index']; ?></td>
                                    <td class="fw-bold">
                                        <?php if($m['parent_id']): ?>
                                            <span class="ps-3 text-muted"><i class="fas fa-level-up-alt fa-rotate-90 me-2"></i></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($m['name']); ?>
                                    </td>
                                    <td><span class="text-primary"><?php echo htmlspecialchars($m['url']); ?></span></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end align-items-center">
                                            <!-- Edit Btn -->
                                            <button class="btn btn-primary btn-sm btn-custom px-3 me-2 edit-menu-btn" 
                                                data-id="<?php echo $m['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($m['name']); ?>"
                                                data-url="<?php echo htmlspecialchars($m['url']); ?>"
                                                data-location="<?php echo $m['menu_location']; ?>"
                                                data-order="<?php echo $m['order_index']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Del Btn -->
                                            <form method="POST" class="m-0" onsubmit="return confirm('WARNING: Deleting a parent menu will also delete all its sub-links. Continue?');">
    <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_menu">
                                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">No navigation menus found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> <!-- End Menus Tab -->

    <!-- Page Hero Banners Tab -->
    <div class="tab-pane fade <?php echo $active_tab == 'banners' ? 'show active' : ''; ?>" id="tab-banners" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="max-width: 800px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="fw-bold m-0"><i class="fas fa-images me-2 text-primary"></i>Page Hero Banners</h5>
                <p class="text-muted small mb-0 mt-1">Upload custom background banners for pages. If left empty, the site seamlessly falls back to the default Theme Service color.</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="manage_settings.php?tab=banners" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="banners_settings_update" value="1">
                    
                    <div class="row g-4">
                        <?php 
                        $hero_pages = [
                            'home' => 'Home Page',
                            'about' => 'About Page',
                            'contact' => 'Contact Page',
                            'support' => 'Support Page',
                            'faq' => 'FAQ Page',
                            'policy' => 'Policy Pages (Privacy, Terms, Shipping...)',
                            'category' => 'Category Pages',
                            'product' => 'Product Details Page',
                        ];
                        
                        foreach ($hero_pages as $key => $label):
                            $setting_key = "hero_banner_{$key}";
                            $current_img = $current_settings[$setting_key] ?? '';
                        ?>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label fw-bold mb-3"><?php echo htmlspecialchars($label); ?></label>
                            
                            <?php if(!empty($current_img) && file_exists('../assets/images/' . $current_img)): ?>
                                <div class="mb-3 position-relative">
                                    <img src="../assets/images/<?php echo htmlspecialchars($current_img); ?>" class="img-fluid rounded shadow-sm" style="max-height: 120px; width: 100%; object-fit: cover;">
                                </div>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <input type="file" name="<?php echo $setting_key; ?>" class="form-control form-control-sm" accept="image/*">
                                        <small class="text-muted">Upload to replace.</small>
                                    </div>
                                    <div class="form-check p-0 m-0 ms-2 d-flex align-items-center">
                                        <input type="checkbox" class="btn-check" name="delete_<?php echo $setting_key; ?>" id="del_<?php echo $setting_key; ?>" value="1" autocomplete="off">
                                        <label class="btn btn-outline-danger btn-sm m-0" for="del_<?php echo $setting_key; ?>"><i class="fas fa-trash"></i> Remove</label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-3 bg-light rounded d-flex align-items-center justify-content-center text-muted border border-dashed" style="height: 120px; border-style: dashed !important; border-color: #dee2e6 !important;">
                                    <div class="text-center">
                                        <i class="fas fa-image fs-4 mb-2"></i>
                                        <div class="small">Default Blue Background Active</div>
                                    </div>
                                </div>
                                <div>
                                    <input type="file" name="<?php echo $setting_key; ?>" class="form-control form-control-sm" accept="image/*">
                                </div>
                            <?php endif; ?>
                            
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top text-end">
                        <button type="submit" class="btn btn-primary btn-custom px-4"><i class="fas fa-save me-2"></i>Save Page Banners</button>
                    </div>
                </form>
            </div>
        </div>
    </div> <!-- End Page Hero Banners Tab -->

</div> <!-- End Tab Content -->

<!-- Add Menu Modal -->
<div class="modal fade" id="addMenuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Navigation Link</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_menu">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link Title</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Destination URL</label>
                        <input type="text" name="url" class="form-control" placeholder="/about.php or # for dropdown">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Menu Location</label>
                        <select name="menu_location" class="form-select" required>
                            <option value="header">Top Header Navigation</option>
                            <option value="footer1">Footer (Column 2)</option>
                            <option value="footer2">Footer (Column 3)</option>
                            <option value="footer3">Footer (Column 4)</option>
                            <option value="both1">Top Header Navigation + Footer (Column 2)</option>
                            <option value="both2">Top Header Navigation + Footer (Column 3)</option>
                            <option value="both3">Top Header Navigation + Footer (Column 4)</option>
                        </select>
                        <small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Dropdown nesting is only supported in the Header navigation.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Parent Menu (Dropdown - Header Only)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- Top Level Link (No Parent) --</option>
                            <?php 
                            if($top_menus && $top_menus->num_rows > 0) {
                                $top_menus->data_seek(0);
                                while($tm = $top_menus->fetch_assoc()) {
                                    echo "<option value='".$tm['id']."'>".$tm['name']."</option>";
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted">Select a parent to nest this link inside a dropdown.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Display Order</label>
                        <input type="number" name="order_index" class="form-control" value="10">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom px-4">Add Menu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Menu Modal -->
<div class="modal fade" id="editMenuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Navigation Link</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_menu">
                    <input type="hidden" name="id" id="edit_m_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link Title</label>
                        <input type="text" name="name" id="edit_m_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Destination URL</label>
                        <input type="text" name="url" id="edit_m_url" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Menu Location</label>
                        <select name="menu_location" id="edit_m_location" class="form-select" required>
                            <option value="header">Top Header Navigation</option>
                            <option value="footer1">Footer (Column 2)</option>
                            <option value="footer2">Footer (Column 3)</option>
                            <option value="footer3">Footer (Column 4)</option>
                            <option value="both1">Top Header Navigation + Footer (Column 2)</option>
                            <option value="both2">Top Header Navigation + Footer (Column 3)</option>
                            <option value="both3">Top Header Navigation + Footer (Column 4)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Display Order</label>
                        <input type="number" name="order_index" id="edit_m_order" class="form-control" required>
                    </div>
                    
                    <div class="alert alert-info py-2 m-0"><i class="fas fa-info-circle me-2"></i>Hierarchy structure editing is disabled here. Delete and recreate if it needs to move.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand("copy");
    alert("Redirect URI copied to clipboard!");
}

document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-menu-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_m_id').value = this.dataset.id;
            document.getElementById('edit_m_name').value = this.dataset.name;
            document.getElementById('edit_m_url').value = this.dataset.url;
            document.getElementById('edit_m_location').value = this.dataset.location;
            document.getElementById('edit_m_order').value = this.dataset.order;
            
            var modal = new mdb.Modal(document.getElementById('editMenuModal'));
            modal.show();
        });
    });

    const testGoogleBtn = document.getElementById('testGoogleConnBtn');
    if (testGoogleBtn) {
        testGoogleBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('google_client_id', document.querySelector('input[name="google_client_id"]').value);
            formData.append('google_client_secret', document.querySelector('input[name="google_client_secret"]').value);

            testGoogleBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            testGoogleBtn.disabled = true;

            fetch('test_google_connection.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                testGoogleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Test Connection';
                testGoogleBtn.disabled = false;
                if(data.success) {
                    alert('Success: ' + data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                testGoogleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Test Connection';
                testGoogleBtn.disabled = false;
                alert('Request failed. Check console for details.');
                console.error(err);
            });
        });
    }
});
</script>

<?php include 'admin_footer.php'; ?>
