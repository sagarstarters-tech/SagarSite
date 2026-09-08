<?php
/**
 * Unified Administrator & Site Details Management Hub
 * 
 * Allows managing:
 * 1. Administrators: List, Add, Edit, Delete (with security safeguards against self-lockout).
 * 2. Website Identity & Core Details: Branding, contact, footer, social, regional.
 * 3. All Site Settings / Custom Parameters: Searchable key-value table with Add, Edit, Delete.
 * 
 * Reuses existing architecture, `users` table (`role='admin'`), and `settings` table.
 */

// Include admin header (handles session, db connection, AuthMiddleware, CSRF verification)
include 'admin_header.php';

// Determine active tab
$active_tab = $_GET['tab'] ?? 'admins';
if (!in_array($active_tab, ['admins', 'site_details', 'custom_keys'])) {
    $active_tab = 'admins';
}

// Helper function to update or insert a setting safely
if (!function_exists('save_site_setting')) {
    function save_site_setting($conn, $key, $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Protected core system setting keys that cannot be deleted
$protected_system_keys = [
    'admin_email',
    'timezone',
    'currency_symbol',
    'maintenance_mode',
    'header_logo_image',
    'footer_logo_image',
    'admin_logo_image',
    'admin_logo_height',
    'site_name',
    'site_version',
    'auto_version_enabled'
];

// ─────────────────────────────────────────────────────────────────────────────
// POST REQUEST HANDLING
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // ACTION 1: ADD ADMINISTRATOR
    // ==========================================
    if ($action === 'add_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? 'India');
        $zip_code = trim($_POST['zip_code'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            set_flash('danger', 'Name, Email, and Password are required fields.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please enter a valid email address.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        if ($check_res && $check_res->num_rows > 0) {
            set_flash('danger', 'An account with this email address already exists.');
            $check_stmt->close();
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }
        $check_stmt->close();

        // Handle Profile Photo Upload
        $profile_photo = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['profile_photo']['tmp_name'];
            $file_name = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $new_filename = 'profile/admin_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $upload_dir = '../uploads/images/profile/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                if (@move_uploaded_file($tmp_name, '../uploads/images/' . $new_filename)) {
                    $profile_photo = $new_filename;
                }
            }
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $role = 'admin';
        $is_verified = 1;

        $ins_stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, city, state, country, zip_code, profile_photo, role, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($ins_stmt) {
            $ins_stmt->bind_param("sssssssssssi", $name, $email, $hashed_password, $phone, $address, $city, $state, $country, $zip_code, $profile_photo, $role, $is_verified);
            if ($ins_stmt->execute()) {
                set_flash('success', "Administrator <strong>" . htmlspecialchars($name) . "</strong> has been created successfully.");
            } else {
                set_flash('danger', "Database error creating administrator: " . $conn->error);
            }
            $ins_stmt->close();
        } else {
            set_flash('danger', "Failed to prepare insert statement: " . $conn->error);
        }

        header("Location: manage_admin_site.php?tab=admins");
        exit;
    }

    // ==========================================
    // ACTION 2: EDIT ADMINISTRATOR
    // ==========================================
    elseif ($action === 'edit_admin') {
        $admin_id = intval($_POST['admin_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');

        if ($admin_id <= 0 || empty($name) || empty($email)) {
            set_flash('danger', 'Invalid administrator details provided.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        // Check if admin exists
        $fetch_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
        $fetch_stmt->bind_param("i", $admin_id);
        $fetch_stmt->execute();
        $existing_admin = $fetch_stmt->get_result()->fetch_assoc();
        $fetch_stmt->close();

        if (!$existing_admin) {
            set_flash('danger', 'Administrator account not found.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        // Check email uniqueness
        $email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_stmt->bind_param("si", $email, $admin_id);
        $email_stmt->execute();
        if ($email_stmt->get_result()->num_rows > 0) {
            set_flash('danger', 'This email is already in use by another user.');
            $email_stmt->close();
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }
        $email_stmt->close();

        // Optional Password Update
        $password_sql = "";
        $types = "sssssss";
        $params = [&$name, &$email, &$phone, &$address, &$city, &$state, &$country];

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $password_sql = ", password = ?";
            $types .= "s";
            $params[] = &$hashed;
        }

        // Optional Profile Photo Update
        $photo_sql = "";
        $new_photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['profile_photo']['tmp_name'];
            $file_name = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $new_name = 'profile/admin_' . $admin_id . '_' . time() . '.' . $ext;
                $upload_dir = '../uploads/images/profile/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                if (@move_uploaded_file($tmp_name, '../uploads/images/' . $new_name)) {
                    $new_photo_path = $new_name;
                    $photo_sql = ", profile_photo = ?";
                    $types .= "s";
                    $params[] = &$new_photo_path;
                }
            }
        }

        $types .= "si";
        $params[] = &$zip_code;
        $params[] = &$admin_id;

        $update_sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, country = ? {$password_sql} {$photo_sql}, zip_code = ? WHERE id = ? AND role = 'admin'";
        $upd_stmt = $conn->prepare($update_sql);
        if ($upd_stmt) {
            $upd_stmt->bind_param($types, ...$params);
            if ($upd_stmt->execute()) {
                // If editing self, sync session
                if ($admin_id == $_SESSION['user_id']) {
                    $_SESSION['name'] = $name;
                    if ($new_photo_path) {
                        $_SESSION['profile_photo'] = $new_photo_path;
                    }
                }
                set_flash('success', "Administrator <strong>" . htmlspecialchars($name) . "</strong> updated successfully.");
            } else {
                set_flash('danger', "Error updating administrator: " . $conn->error);
            }
            $upd_stmt->close();
        } else {
            set_flash('danger', "Failed to prepare update query: " . $conn->error);
        }

        header("Location: manage_admin_site.php?tab=admins");
        exit;
    }

    // ==========================================
    // ACTION 3: DELETE ADMINISTRATOR
    // ==========================================
    elseif ($action === 'delete_admin') {
        $admin_id = intval($_POST['admin_id'] ?? 0);

        // Security check 1: Cannot delete self
        if ($admin_id == $_SESSION['user_id']) {
            set_flash('danger', 'Security Restriction: You cannot delete your own active administrator account.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        // Security check 2: Cannot delete if only 1 admin remains on the website
        $count_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'");
        $total_admins = (int)($count_res ? $count_res->fetch_assoc()['c'] : 0);
        if ($total_admins <= 1) {
            set_flash('danger', 'Security Restriction: Cannot delete the last remaining administrator account. The website must have at least one active administrator.');
            header("Location: manage_admin_site.php?tab=admins");
            exit;
        }

        // Get admin name for feedback
        $n_res = $conn->query("SELECT name FROM users WHERE id = $admin_id AND role = 'admin'");
        $del_name = ($n_res && $row = $n_res->fetch_assoc()) ? $row['name'] : "#$admin_id";

        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        if ($del_stmt) {
            $del_stmt->bind_param("i", $admin_id);
            if ($del_stmt->execute() && $del_stmt->affected_rows > 0) {
                set_flash('success', "Administrator <strong>" . htmlspecialchars($del_name) . "</strong> has been deleted.");
            } else {
                set_flash('danger', "Administrator could not be deleted or was not found.");
            }
            $del_stmt->close();
        }

        header("Location: manage_admin_site.php?tab=admins");
        exit;
    }

    // ==========================================
    // ACTION 4: UPDATE WEBSITE DETAILS
    // ==========================================
    elseif ($action === 'update_site_details') {
        // Core Site Identity
        if (isset($_POST['site_name'])) save_site_setting($conn, 'site_name', trim($_POST['site_name']));
        if (isset($_POST['header_announcement'])) save_site_setting($conn, 'header_announcement', trim($_POST['header_announcement']));
        
        // Logo Display & Heights
        $show_header_logo = isset($_POST['show_header_logo']) ? '1' : '0';
        save_site_setting($conn, 'show_header_logo', $show_header_logo);
        if (isset($_POST['header_logo_height'])) save_site_setting($conn, 'header_logo_height', (string)intval($_POST['header_logo_height']));

        $show_footer_logo = isset($_POST['show_footer_logo']) ? '1' : '0';
        save_site_setting($conn, 'show_footer_logo', $show_footer_logo);
        if (isset($_POST['footer_logo_height'])) save_site_setting($conn, 'footer_logo_height', (string)intval($_POST['footer_logo_height']));

        // Header Logo Image Upload
        if (isset($_FILES['header_logo_image']) && $_FILES['header_logo_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['header_logo_image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['header_logo_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $new_logo = 'logo_' . time() . '.' . $ext;
                $upload_dir = '../uploads/media/images/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                if (@move_uploaded_file($tmp, $upload_dir . $new_logo)) {
                    save_site_setting($conn, 'header_logo_image', 'uploads/media/images/' . $new_logo);
                } elseif (@move_uploaded_file($tmp, '../assets/images/' . $new_logo)) {
                    save_site_setting($conn, 'header_logo_image', 'assets/images/' . $new_logo);
                }
            }
        }

        // Footer Logo Image Upload
        if (isset($_FILES['footer_logo_image']) && $_FILES['footer_logo_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['footer_logo_image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['footer_logo_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $new_flogo = 'footer_logo_' . time() . '.' . $ext;
                $upload_dir = '../uploads/media/images/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                if (@move_uploaded_file($tmp, $upload_dir . $new_flogo)) {
                    save_site_setting($conn, 'footer_logo_image', 'uploads/media/images/' . $new_flogo);
                } elseif (@move_uploaded_file($tmp, '../assets/images/' . $new_flogo)) {
                    save_site_setting($conn, 'footer_logo_image', 'assets/images/' . $new_flogo);
                }
            }
        }

        // Admin Panel Sidebar Logo Upload
        if (isset($_FILES['admin_logo_image']) && $_FILES['admin_logo_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['admin_logo_image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['admin_logo_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $new_adm_logo = 'admin_logo_' . time() . '.' . $ext;
                $upload_dir = '../uploads/media/images/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                if (@move_uploaded_file($tmp, $upload_dir . $new_adm_logo)) {
                    save_site_setting($conn, 'admin_logo_image', 'uploads/media/images/' . $new_adm_logo);
                } elseif (@move_uploaded_file($tmp, '../assets/images/' . $new_adm_logo)) {
                    save_site_setting($conn, 'admin_logo_image', 'assets/images/' . $new_adm_logo);
                }
            }
        }
        if (isset($_POST['admin_logo_height'])) {
            save_site_setting($conn, 'admin_logo_height', (string)intval($_POST['admin_logo_height']));
        }

        // Contact & Location Details
        if (isset($_POST['admin_email'])) save_site_setting($conn, 'admin_email', trim($_POST['admin_email']));
        if (isset($_POST['contact_email'])) save_site_setting($conn, 'contact_email', trim($_POST['contact_email']));
        if (isset($_POST['contact_phone'])) save_site_setting($conn, 'contact_phone', trim($_POST['contact_phone']));
        if (isset($_POST['whatsapp_number'])) save_site_setting($conn, 'whatsapp_number', trim($_POST['whatsapp_number']));
        if (isset($_POST['contact_address'])) save_site_setting($conn, 'contact_address', trim($_POST['contact_address']));
        if (isset($_POST['contact_hours'])) save_site_setting($conn, 'contact_hours', trim($_POST['contact_hours']));
        if (isset($_POST['invoice_gst_number'])) save_site_setting($conn, 'invoice_gst_number', trim($_POST['invoice_gst_number']));
        if (isset($_POST['contact_map_embed'])) save_site_setting($conn, 'contact_map_embed', trim($_POST['contact_map_embed']));
        $contact_map_show = isset($_POST['contact_map_show']) ? '1' : '0';
        save_site_setting($conn, 'contact_map_show', $contact_map_show);

        // Footer & Copyright
        if (isset($_POST['footer_text'])) save_site_setting($conn, 'footer_text', trim($_POST['footer_text']));
        if (isset($_POST['footer_copyright'])) save_site_setting($conn, 'footer_copyright', trim($_POST['footer_copyright']));
        if (isset($_POST['footer_col2_title'])) save_site_setting($conn, 'footer_col2_title', trim($_POST['footer_col2_title']));
        if (isset($_POST['footer_col3_title'])) save_site_setting($conn, 'footer_col3_title', trim($_POST['footer_col3_title']));
        if (isset($_POST['footer_col4_title'])) save_site_setting($conn, 'footer_col4_title', trim($_POST['footer_col4_title']));

        // Social Media Links
        if (isset($_POST['social_facebook'])) save_site_setting($conn, 'social_facebook', trim($_POST['social_facebook']));
        if (isset($_POST['social_instagram'])) save_site_setting($conn, 'social_instagram', trim($_POST['social_instagram']));
        if (isset($_POST['social_twitter'])) save_site_setting($conn, 'social_twitter', trim($_POST['social_twitter']));
        if (isset($_POST['social_youtube'])) save_site_setting($conn, 'social_youtube', trim($_POST['social_youtube']));
        if (isset($_POST['social_linkedin'])) save_site_setting($conn, 'social_linkedin', trim($_POST['social_linkedin']));
        if (isset($_POST['social_whatsapp'])) save_site_setting($conn, 'social_whatsapp', trim($_POST['social_whatsapp']));

        // Regional & System Defaults
        if (isset($_POST['timezone'])) save_site_setting($conn, 'timezone', trim($_POST['timezone']));
        if (isset($_POST['currency_symbol'])) save_site_setting($conn, 'currency_symbol', trim($_POST['currency_symbol']));
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        save_site_setting($conn, 'maintenance_mode', $maintenance_mode);

        set_flash('success', 'Website details have been updated successfully.');
        header("Location: manage_admin_site.php?tab=site_details");
        exit;
    }

    // ==========================================
    // ACTION 5: ADD CUSTOM SETTING KEY
    // ==========================================
    elseif ($action === 'add_custom_setting') {
        $raw_key = trim($_POST['setting_key'] ?? '');
        $setting_value = trim($_POST['setting_value'] ?? '');

        // Sanitize setting key (only letters, numbers, underscores)
        $setting_key = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $raw_key));

        if (empty($setting_key)) {
            set_flash('danger', 'Setting Key cannot be empty.');
            header("Location: manage_admin_site.php?tab=custom_keys");
            exit;
        }

        // Check if exists
        $chk_q = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
        $chk_q->bind_param("s", $setting_key);
        $chk_q->execute();
        if ($chk_q->get_result()->num_rows > 0) {
            set_flash('danger', "Setting Key '<strong>{$setting_key}</strong>' already exists. You can edit its value below.");
            $chk_q->close();
            header("Location: manage_admin_site.php?tab=custom_keys");
            exit;
        }
        $chk_q->close();

        save_site_setting($conn, $setting_key, $setting_value);
        set_flash('success', "New custom setting '<strong>{$setting_key}</strong>' added successfully.");
        header("Location: manage_admin_site.php?tab=custom_keys");
        exit;
    }

    // ==========================================
    // ACTION 6: EDIT CUSTOM SETTING KEY
    // ==========================================
    elseif ($action === 'edit_custom_setting') {
        $setting_key = trim($_POST['setting_key'] ?? '');
        $setting_value = $_POST['setting_value'] ?? '';

        if (empty($setting_key)) {
            set_flash('danger', 'Setting Key is required.');
            header("Location: manage_admin_site.php?tab=custom_keys");
            exit;
        }

        save_site_setting($conn, $setting_key, $setting_value);
        set_flash('success', "Setting '<strong>" . htmlspecialchars($setting_key) . "</strong>' updated successfully.");
        header("Location: manage_admin_site.php?tab=custom_keys");
        exit;
    }

    // ==========================================
    // ACTION 7: DELETE CUSTOM SETTING KEY
    // ==========================================
    elseif ($action === 'delete_custom_setting') {
        $setting_key = trim($_POST['setting_key'] ?? '');

        if (in_array($setting_key, $protected_system_keys)) {
            set_flash('danger', "Security Safeguard: '<strong>" . htmlspecialchars($setting_key) . "</strong>' is a protected core system parameter and cannot be deleted. You can edit its value instead.");
            header("Location: manage_admin_site.php?tab=custom_keys");
            exit;
        }

        $del_s = $conn->prepare("DELETE FROM settings WHERE setting_key = ?");
        if ($del_s) {
            $del_s->bind_param("s", $setting_key);
            if ($del_s->execute() && $del_s->affected_rows > 0) {
                set_flash('success', "Setting '<strong>" . htmlspecialchars($setting_key) . "</strong>' was deleted.");
            } else {
                set_flash('danger', "Could not delete setting key or key does not exist.");
            }
            $del_s->close();
        }

        header("Location: manage_admin_site.php?tab=custom_keys");
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DATA FETCHING FOR VIEW
// ─────────────────────────────────────────────────────────────────────────────

// Refresh settings into local array
$all_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $all_settings[$r['setting_key']] = $r['setting_value'];
    }
}

// Fetch all administrators
$admins_res = $conn->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC");
$admin_list = [];
if ($admins_res) {
    while ($row = $admins_res->fetch_assoc()) {
        $admin_list[] = $row;
    }
}
$admin_count = count($admin_list);
?>

<div class="container-fluid px-4 py-4 adm-wrapper">

    <!-- ── Hero Banner ──────────────────────────────────────────────────── -->
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-user-shield"></i> Unified Control Center
            </div>
            <h1 class="adm-hero-title">Administrator &amp; Site Details</h1>
            <p class="adm-hero-subtitle">Manage all Website Administrators and entire Store Details (Branding, Contact, Footer, Social, and Custom Settings) from one single place.</p>
        </div>
        <div class="adm-hero-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-light shadow-sm fw-semibold rounded-pill px-3" data-mdb-toggle="modal" data-mdb-target="#addAdminModal">
                <i class="fas fa-user-plus text-primary me-2"></i>Add Administrator
            </button>
            <button type="button" class="btn btn-outline-light rounded-pill px-3" data-mdb-toggle="modal" data-mdb-target="#addCustomKeyModal">
                <i class="fas fa-plus-circle me-2"></i>Add Site Parameter
            </button>
        </div>
    </div>

    <!-- ── Quick Statistics Pills ────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-users-cog fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Active Administrators</div>
                        <h4 class="fw-bold mb-0 text-dark"><?php echo $admin_count; ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-globe fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Store Name</div>
                        <h5 class="fw-bold mb-0 text-truncate" style="max-width: 220px;">
                            <?php echo htmlspecialchars($all_settings['site_name'] ?? "Sagar Starter's"); ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-sliders-h fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Config Parameters</div>
                        <h4 class="fw-bold mb-0 text-dark"><?php echo count($all_settings); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter Tabs Navigation ────────────────────────────────────────── -->
    <div class="adm-filter-tabs mb-4">
        <a class="adm-filter-tab <?php echo $active_tab === 'admins' ? 'active' : ''; ?>" href="manage_admin_site.php?tab=admins">
            <i class="fas fa-users-cog me-2"></i>Administrators (<?php echo $admin_count; ?>)
        </a>
        <a class="adm-filter-tab <?php echo $active_tab === 'site_details' ? 'active' : ''; ?>" href="manage_admin_site.php?tab=site_details">
            <i class="fas fa-store me-2"></i>Website Identity &amp; Details
        </a>
        <a class="adm-filter-tab <?php echo $active_tab === 'custom_keys' ? 'active' : ''; ?>" href="manage_admin_site.php?tab=custom_keys">
            <i class="fas fa-database me-2"></i>All Parameters / Custom Details
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 1: ADMINISTRATORS
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($active_tab === 'admins'): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="fas fa-user-shield me-2 text-primary"></i>Website Administrators
                </h5>
                <p class="text-muted small mb-0">System users with full administrative access to manage products, orders, and system configurations.</p>
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-mdb-toggle="modal" data-mdb-target="#addAdminModal">
                <i class="fas fa-plus me-2"></i>Add New Administrator
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4" style="width: 70px;">ID</th>
                            <th>Administrator</th>
                            <th>Email Address</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created On</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admin_list as $adm): 
                            $is_current_user = ($adm['id'] == $_SESSION['user_id']);
                            $avatar_url = resolve_profile_photo_url($adm['profile_photo'] ?? '', 'admin');
                        ?>
                        <tr class="<?php echo $is_current_user ? 'table-light' : ''; ?>">
                            <td class="ps-4 fw-bold text-muted">#<?php echo $adm['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="position-relative me-3">
                                        <?php if (!empty($avatar_url)): ?>
                                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($adm['name']); ?>" class="rounded-circle shadow-sm" style="width: 44px; height: 44px; object-fit: cover;" onerror="this.outerHTML='<div class=\'rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold\' style=\'width:44px;height:44px;\'><?php echo strtoupper(substr($adm['name'], 0, 1)); ?></div>';">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                                                <?php echo strtoupper(substr($adm['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                            <span><?php echo htmlspecialchars($adm['name']); ?></span>
                                            <?php if ($is_current_user): ?>
                                                <span class="badge bg-primary rounded-pill px-2 py-1 small">You (Active)</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted small">Role: Super Administrator</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($adm['email']); ?>" class="text-decoration-none text-dark">
                                    <i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($adm['email']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($adm['phone'])): ?>
                                    <span class="text-dark"><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($adm['phone']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1">
                                    <i class="fas fa-check-circle me-1"></i>Active
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?php echo date('M d, Y', strtotime($adm['created_at'] ?? 'now')); ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1 edit-admin-btn"
                                    data-id="<?php echo $adm['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($adm['name']); ?>"
                                    data-email="<?php echo htmlspecialchars($adm['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($adm['phone'] ?? ''); ?>"
                                    data-address="<?php echo htmlspecialchars($adm['address'] ?? ''); ?>"
                                    data-city="<?php echo htmlspecialchars($adm['city'] ?? ''); ?>"
                                    data-state="<?php echo htmlspecialchars($adm['state'] ?? ''); ?>"
                                    data-country="<?php echo htmlspecialchars($adm['country'] ?? ''); ?>"
                                    data-zip="<?php echo htmlspecialchars($adm['zip_code'] ?? ''); ?>"
                                    data-photo="<?php echo htmlspecialchars($avatar_url); ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <?php if (!$is_current_user && $admin_count > 1): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 delete-admin-btn"
                                        data-id="<?php echo $adm['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($adm['name']); ?>">
                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                    </button>
                                <?php elseif ($is_current_user): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled title="You cannot delete your own account">
                                        <i class="fas fa-lock me-1"></i>Self
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled title="At least one administrator must remain">
                                        <i class="fas fa-shield-alt me-1"></i>Protected
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 2: WEBSITE IDENTITY & DETAILS
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($active_tab === 'site_details'): ?>
    <form method="POST" action="manage_admin_site.php?tab=site_details" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="update_site_details">

        <div class="row g-4">

            <!-- Section 1: Store Branding & Identity -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-1 text-primary">
                            <i class="fas fa-store me-2"></i>Store Identity &amp; Branding
                        </h5>
                        <p class="text-muted small mb-0">Core branding used across navigation, headers, emails, and invoices.</p>
                    </div>
                    <div class="card-body px-4 py-3">

                        <!-- Site Name -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Website / Store Name <span class="text-danger">*</span></label>
                            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($all_settings['site_name'] ?? "Sagar Starter's"); ?>" required placeholder="e.g. Sagar Starter's">
                            <div class="form-text">Displayed on title tags, navbar, invoices, and system notifications.</div>
                        </div>

                        <!-- Announcement Bar -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Header Announcement / Notification Text</label>
                            <input type="text" name="header_announcement" class="form-control" value="<?php echo htmlspecialchars($all_settings['header_announcement'] ?? ''); ?>" placeholder="e.g. GSTIN: 09BWSPS4806F1ZZ | Free Delivery on orders over ₹1000">
                            <div class="form-text">Top marquee bar announcement visible on the store header.</div>
                        </div>

                        <!-- Header Logo -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold mb-0 text-dark">Header Logo</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_header_logo" id="show_header_logo" value="1" <?php echo (!isset($all_settings['show_header_logo']) || $all_settings['show_header_logo'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="show_header_logo">Visible</label>
                                </div>
                            </div>
                            <?php 
                            $h_logo = $all_settings['header_logo_image'] ?? 'logo.jpg';
                            $h_logo_url = resolve_image_url($h_logo);
                            ?>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="p-2 bg-white rounded border text-center" style="min-width: 100px;">
                                    <img src="<?php echo htmlspecialchars($h_logo_url); ?>" alt="Header Logo" style="max-height: 40px; max-width: 120px; object-fit: contain;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="header_logo_image" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-muted mt-1">Recommended: Transparent PNG or WebP.</div>
                                </div>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-auto">
                                    <label class="small text-muted mb-0">Logo Height (px):</label>
                                </div>
                                <div class="col-4">
                                    <input type="number" name="header_logo_height" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['header_logo_height'] ?? '35'); ?>" min="20" max="120">
                                </div>
                            </div>
                        </div>

                        <!-- Footer Logo -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold mb-0 text-dark">Footer Logo</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_footer_logo" id="show_footer_logo" value="1" <?php echo (!isset($all_settings['show_footer_logo']) || $all_settings['show_footer_logo'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="show_footer_logo">Visible</label>
                                </div>
                            </div>
                            <?php 
                            $f_logo = $all_settings['footer_logo_image'] ?? 'logo.jpg';
                            $f_logo_url = resolve_image_url($f_logo);
                            ?>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="p-2 bg-white rounded border text-center" style="min-width: 100px;">
                                    <img src="<?php echo htmlspecialchars($f_logo_url); ?>" alt="Footer Logo" style="max-height: 40px; max-width: 120px; object-fit: contain;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="footer_logo_image" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-muted mt-1">Recommended: Transparent PNG, WebP or SVG.</div>
                                </div>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-auto">
                                    <label class="small text-muted mb-0">Logo Height (px):</label>
                                </div>
                                <div class="col-4">
                                    <input type="number" name="footer_logo_height" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['footer_logo_height'] ?? '35'); ?>" min="20" max="120">
                                </div>
                            </div>
                        </div>

                        <!-- Admin Panel Logo (Sidebar) -->
                        <div class="mb-3 p-3 bg-light rounded-3 border" style="border-left: 4px solid #1b3c53 !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                                    <i class="fas fa-shield-alt text-primary"></i>
                                    <span>Admin Panel Logo (Sidebar)</span>
                                </label>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-0.5 small">Admin Sidebar</span>
                            </div>
                            <?php 
                            $adm_logo = !empty($all_settings['admin_logo_image']) ? $all_settings['admin_logo_image'] : ($all_settings['header_logo_image'] ?? 'logo.jpg');
                            $adm_logo_url = resolve_image_url($adm_logo);
                            if (empty($adm_logo_url) || strpos($adm_logo_url, 'placeholder') !== false) {
                                $fallback = file_exists(BASE_PATH . '/assets/images/logo.jpg') ? 'logo.jpg' : 'logo_1772118384.jpeg';
                                $adm_logo_url = ASSETS_URL . '/images/' . $fallback;
                            }
                            ?>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="p-2 rounded border text-center" style="min-width: 100px; background: #132435;">
                                    <img src="<?php echo htmlspecialchars($adm_logo_url); ?>" alt="Admin Panel Logo" style="max-height: 45px; max-width: 120px; object-fit: contain;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="admin_logo_image" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-muted mt-1">Displayed at the top of the Admin Panel left sidebar. Recommended: PNG, JPG, or SVG on dark background.</div>
                                </div>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-auto">
                                    <label class="small text-muted mb-0">Admin Logo Height (px):</label>
                                </div>
                                <div class="col-4">
                                    <input type="number" name="admin_logo_height" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['admin_logo_height'] ?? '45'); ?>" min="20" max="120">
                                </div>
                            </div>
                        </div>

                        <!-- System Defaults -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Currency Symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($all_settings['currency_symbol'] ?? '₹'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Default Timezone</label>
                                <select name="timezone" class="form-select">
                                    <?php 
                                    $tz_current = $all_settings['timezone'] ?? 'Asia/Kolkata';
                                    $timezones = ['Asia/Kolkata', 'UTC', 'America/New_York', 'Europe/London', 'Asia/Dubai', 'Asia/Singapore'];
                                    foreach ($timezones as $tz):
                                    ?>
                                        <option value="<?php echo $tz; ?>" <?php echo ($tz_current === $tz) ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 p-3 rounded-3 border bg-light">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo (isset($all_settings['maintenance_mode']) && $all_settings['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold text-dark" for="maintenance_mode">Maintenance Mode</label>
                                <div class="small text-muted">When enabled, visitors see a maintenance page while admins can still browse.</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Section 2: Contact, Office & Communication -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-1 text-primary">
                            <i class="fas fa-headset me-2"></i>Contact, Office &amp; Support
                        </h5>
                        <p class="text-muted small mb-0">Direct communication channels visible on Contact Us, Header, Footer, and Invoices.</p>
                    </div>
                    <div class="card-body px-4 py-3">

                        <!-- Primary Admin Email -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Admin System Email <span class="text-danger">*</span></label>
                                <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($all_settings['admin_email'] ?? ''); ?>" required placeholder="admin@example.com">
                                <div class="form-text">Receives order alerts and critical system messages.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Public Contact Email</label>
                                <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($all_settings['contact_email'] ?? ''); ?>" placeholder="support@yourstore.com">
                                <div class="form-text">Shown to visitors on Contact Us page and Footer.</div>
                            </div>
                        </div>

                        <!-- Phone & WhatsApp -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Contact Phone Number</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($all_settings['contact_phone'] ?? ''); ?>" placeholder="+91 9876543210">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Official WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($all_settings['whatsapp_number'] ?? ''); ?>" placeholder="918573934013 (with country code)">
                            </div>
                        </div>

                        <!-- Physical Office Address -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Store / Business Address</label>
                            <textarea name="contact_address" class="form-control" rows="2" placeholder="Full physical store or warehouse address"><?php echo htmlspecialchars($all_settings['contact_address'] ?? ''); ?></textarea>
                        </div>

                        <!-- Business Working Hours -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Business / Working Hours</label>
                                <input type="text" name="contact_hours" class="form-control" value="<?php echo htmlspecialchars($all_settings['contact_hours'] ?? 'Mon–Sat: 9am – 6pm'); ?>" placeholder="e.g. Mon–Sat: 9am – 6pm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Business GSTIN / Tax ID</label>
                                <input type="text" name="invoice_gst_number" class="form-control" value="<?php echo htmlspecialchars($all_settings['invoice_gst_number'] ?? ''); ?>" placeholder="e.g. 09BWSPS4806F1ZZ">
                            </div>
                        </div>

                        <!-- Google Maps Embed -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Google Maps Embed URL</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="contact_map_show" id="contact_map_show" value="1" <?php echo (!isset($all_settings['contact_map_show']) || $all_settings['contact_map_show'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="contact_map_show">Show Map</label>
                                </div>
                            </div>
                            <textarea name="contact_map_embed" class="form-control" rows="2" placeholder="https://www.google.com/maps/embed?..."><?php echo htmlspecialchars($all_settings['contact_map_embed'] ?? ''); ?></textarea>
                            <div class="form-text">Paste the 'src' link from Google Maps 'Embed a map' share menu.</div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Section 3: Footer Content & Legal -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-1 text-primary">
                            <i class="fas fa-shoe-prints me-2"></i>Footer Content &amp; Columns
                        </h5>
                        <p class="text-muted small mb-0">Configure text and titles displayed on the website footer.</p>
                    </div>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Footer Short Bio / About Text</label>
                            <textarea name="footer_text" class="form-control" rows="3" placeholder="Brief statement about the company displayed under footer logo"><?php echo htmlspecialchars($all_settings['footer_text'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Footer Copyright Notice</label>
                            <input type="text" name="footer_copyright" class="form-control" value="<?php echo htmlspecialchars($all_settings['footer_copyright'] ?? 'Copyright © ' . date('Y') . " Sagar Starter's. Powered by Sagar Starter's."); ?>">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark small">Footer Column 2 Title</label>
                                <input type="text" name="footer_col2_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['footer_col2_title'] ?? 'For Him'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark small">Footer Column 3 Title</label>
                                <input type="text" name="footer_col3_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['footer_col3_title'] ?? 'Legal'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark small">Footer Column 4 Title</label>
                                <input type="text" name="footer_col4_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['footer_col4_title'] ?? 'Help'); ?>">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Section 4: Social Media Links -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-1 text-primary">
                            <i class="fas fa-share-alt me-2"></i>Social Channels &amp; Profiles
                        </h5>
                        <p class="text-muted small mb-0">Links rendered as social media icons across header, footer, and contact touchpoints.</p>
                    </div>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark"><i class="fab fa-facebook text-primary me-2"></i>Facebook Page URL</label>
                            <input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars($all_settings['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/yourpage">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark"><i class="fab fa-instagram text-danger me-2"></i>Instagram Profile URL</label>
                            <input type="url" name="social_instagram" class="form-control" value="<?php echo htmlspecialchars($all_settings['social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/yourprofile">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark"><i class="fab fa-twitter text-info me-2"></i>Twitter / X Profile URL</label>
                            <input type="url" name="social_twitter" class="form-control" value="<?php echo htmlspecialchars($all_settings['social_twitter'] ?? ''); ?>" placeholder="https://x.com/yourhandle">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark"><i class="fab fa-youtube text-danger me-2"></i>YouTube Channel URL</label>
                            <input type="url" name="social_youtube" class="form-control" value="<?php echo htmlspecialchars($all_settings['social_youtube'] ?? ''); ?>" placeholder="https://youtube.com/@yourchannel">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark"><i class="fab fa-linkedin text-primary me-2"></i>LinkedIn URL</label>
                                <input type="url" name="social_linkedin" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark"><i class="fab fa-whatsapp text-success me-2"></i>WhatsApp Direct Link</label>
                                <input type="text" name="social_whatsapp" class="form-control form-control-sm" value="<?php echo htmlspecialchars($all_settings['social_whatsapp'] ?? ''); ?>" placeholder="https://wa.me/91...">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Save Floating Bar -->
        <div class="p-3 bg-white rounded-4 shadow-sm border mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted small">
                <i class="fas fa-info-circle text-primary me-1"></i> Changes apply immediately across the entire website.
            </span>
            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                <i class="fas fa-save me-2"></i>Save All Website Details
            </button>
        </div>
    </form>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 3: ALL PARAMETERS & CUSTOM DETAILS MANAGER
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($active_tab === 'custom_keys'): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="fas fa-database me-2 text-primary"></i>All Website Configuration Parameters
                </h5>
                <p class="text-muted small mb-0">Search, add, edit, or delete any site detail or custom parameter across the entire website.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="settingsSearchInput" class="form-control bg-light border-0" placeholder="Filter parameters...">
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-3 shadow-sm" data-mdb-toggle="modal" data-mdb-target="#addCustomKeyModal">
                    <i class="fas fa-plus me-1"></i>New Parameter
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0" id="settingsTable">
                    <thead class="bg-light text-muted small text-uppercase sticky-top" style="z-index: 2;">
                        <tr>
                            <th class="ps-4" style="width: 25%;">Setting Key</th>
                            <th style="width: 45%;">Value</th>
                            <th style="width: 15%;">Type</th>
                            <th class="text-end pe-4" style="width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_settings as $s_key => $s_val): 
                            $is_protected = in_array($s_key, $protected_system_keys);
                            $val_display = (strlen($s_val) > 100) ? substr($s_val, 0, 100) . '...' : $s_val;
                        ?>
                        <tr class="setting-row" data-key="<?php echo htmlspecialchars(strtolower($s_key)); ?>" data-val="<?php echo htmlspecialchars(strtolower($s_val)); ?>">
                            <td class="ps-4">
                                <span class="font-monospace fw-bold text-primary"><?php echo htmlspecialchars($s_key); ?></span>
                                <?php if ($is_protected): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-1.5 py-0.5 ms-1 small" title="Protected core setting">Core</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-break small text-muted" style="max-width: 450px;">
                                    <?php echo htmlspecialchars($val_display); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($s_val === '1' || $s_val === '0'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 py-1 small">Boolean (<?php echo $s_val; ?>)</span>
                                <?php elseif (is_numeric($s_val)): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small">Number</span>
                                <?php elseif (strpos($s_val, 'http') === 0): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small">URL / Link</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small">Text / Config</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 me-1 edit-setting-btn"
                                    data-key="<?php echo htmlspecialchars($s_key); ?>"
                                    data-val="<?php echo htmlspecialchars($s_val); ?>">
                                    <i class="fas fa-pencil-alt me-1"></i>Edit
                                </button>
                                <?php if (!$is_protected): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1 delete-setting-btn"
                                        data-key="<?php echo htmlspecialchars($s_key); ?>">
                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1" disabled title="Core parameter cannot be deleted">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: ADD ADMINISTRATOR
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="addAdminModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Add New Administrator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=admins" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_admin">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Rahul Sharma">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="admin@domain.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Secure Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+91 9876543210">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                            <div class="small text-muted">Allowed formats: JPG, PNG, WEBP.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Office / Residential Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street Address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">City</label>
                            <input type="text" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">State</label>
                            <input type="text" name="state" class="form-control" placeholder="State">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Zip / Postal Code</label>
                            <input type="text" name="zip_code" class="form-control" placeholder="Zip Code">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-link text-muted" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-check me-2"></i>Create Administrator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: EDIT ADMINISTRATOR
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="editAdminModalLabel">
                    <i class="fas fa-user-edit me-2"></i>Edit Administrator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=admins" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="admin_id" id="edit_admin_id" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_admin_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_admin_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">New Password <span class="text-muted small">(Leave blank to keep existing)</span></label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Phone Number</label>
                            <input type="text" name="phone" id="edit_admin_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Replace Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Office / Residential Address</label>
                            <input type="text" name="address" id="edit_admin_address" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">City</label>
                            <input type="text" name="city" id="edit_admin_city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">State</label>
                            <input type="text" name="state" id="edit_admin_state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Zip / Postal Code</label>
                            <input type="text" name="zip_code" id="edit_admin_zip" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-link text-muted" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i>Save Administrator Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: DELETE ADMINISTRATOR CONFIRMATION
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Administrator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=admins">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_admin">
                <input type="hidden" name="admin_id" id="delete_admin_id" value="">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="fas fa-user-times fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Are you sure?</h5>
                    <p class="text-muted mb-0">
                        Are you sure you want to permanently delete administrator <strong id="delete_admin_name_display"></strong>? This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-trash-alt me-2"></i>Yes, Delete Administrator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: ADD CUSTOM SETTING KEY
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addCustomKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus-circle me-2"></i>Add Website Parameter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=custom_keys">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_custom_setting">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Setting Key <span class="text-danger">*</span></label>
                        <input type="text" name="setting_key" class="form-control font-monospace" required placeholder="e.g. store_tagline, support_telegram">
                        <div class="small text-muted">Use lowercase letters, numbers, and underscores only.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Setting Value</label>
                        <textarea name="setting_value" class="form-control" rows="3" placeholder="Enter configuration value..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-link text-muted" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-check me-2"></i>Add Parameter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: EDIT CUSTOM SETTING KEY
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editSettingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-pencil-alt me-2"></i>Edit Website Parameter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=custom_keys">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="edit_custom_setting">
                <input type="hidden" name="setting_key" id="edit_setting_key_hidden" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Setting Key</label>
                        <input type="text" id="edit_setting_key_display" class="form-control font-monospace bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Setting Value</label>
                        <textarea name="setting_value" id="edit_setting_value" class="form-control" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-link text-muted" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i>Save Parameter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     MODAL: DELETE CUSTOM SETTING CONFIRMATION
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteSettingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-trash-alt me-2"></i>Delete Setting Key
                </h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_admin_site.php?tab=custom_keys">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_custom_setting">
                <input type="hidden" name="setting_key" id="delete_setting_key_hidden" value="">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="fas fa-exclamation-circle fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Configuration Parameter?</h5>
                    <p class="text-muted mb-0">
                        Are you sure you want to permanently delete parameter <strong id="delete_setting_key_display" class="font-monospace text-danger"></strong> from the database?
                    </p>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-0 rounded-bottom-4 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-trash-alt me-2"></i>Yes, Delete Parameter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ───────────────────────────────────────────────────────────────────── -->
<!-- JAVASCRIPT INTERACTIONS                                              -->
<!-- ───────────────────────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Edit Admin Modal Population
    document.querySelectorAll('.edit-admin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_admin_id').value = this.dataset.id || '';
            document.getElementById('edit_admin_name').value = this.dataset.name || '';
            document.getElementById('edit_admin_email').value = this.dataset.email || '';
            document.getElementById('edit_admin_phone').value = this.dataset.phone || '';
            document.getElementById('edit_admin_address').value = this.dataset.address || '';
            document.getElementById('edit_admin_city').value = this.dataset.city || '';
            document.getElementById('edit_admin_state').value = this.dataset.state || '';
            document.getElementById('edit_admin_zip').value = this.dataset.zip || '';
            
            const modalEl = document.getElementById('editAdminModal');
            if (window.mdb && mdb.Modal) {
                const modal = mdb.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
        });
    });

    // 2. Delete Admin Modal Population
    document.querySelectorAll('.delete-admin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_admin_id').value = this.dataset.id || '';
            document.getElementById('delete_admin_name_display').textContent = this.dataset.name || ('#' + this.dataset.id);
            
            const modalEl = document.getElementById('deleteAdminModal');
            if (window.mdb && mdb.Modal) {
                const modal = mdb.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
        });
    });

    // 3. Edit Setting Key Modal Population
    document.querySelectorAll('.edit-setting-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.key || '';
            const val = this.dataset.val || '';
            document.getElementById('edit_setting_key_hidden').value = key;
            document.getElementById('edit_setting_key_display').value = key;
            document.getElementById('edit_setting_value').value = val;
            
            const modalEl = document.getElementById('editSettingModal');
            if (window.mdb && mdb.Modal) {
                const modal = mdb.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
        });
    });

    // 4. Delete Setting Key Modal Population
    document.querySelectorAll('.delete-setting-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.key || '';
            document.getElementById('delete_setting_key_hidden').value = key;
            document.getElementById('delete_setting_key_display').textContent = key;
            
            const modalEl = document.getElementById('deleteSettingModal');
            if (window.mdb && mdb.Modal) {
                const modal = mdb.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $(modalEl).modal('show');
            }
        });
    });

    // 5. Client-Side Search / Filter for Custom Parameters Table
    const searchInput = document.getElementById('settingsSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            document.querySelectorAll('#settingsTable .setting-row').forEach(row => {
                const k = row.getAttribute('data-key') || '';
                const v = row.getAttribute('data-val') || '';
                if (k.includes(query) || v.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

});
</script>

<?php include 'admin_footer.php'; ?>
