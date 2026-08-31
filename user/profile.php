<?php
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);

// ── Handle Profile Update (POST) BEFORE rendering any HTML ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: profile.php");
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $_SESSION['error'] = "Name is required.";
        header("Location: profile.php");
        exit;
    }

    $phone_raw = trim($_POST['phone'] ?? '');
    $phone = $phone_raw;
    $phone_clean = str_replace([' ', '-', '(', ')', '+'], '', $phone_raw);

    if (strlen($phone_clean) > 5) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')=? AND id != ?");
        $stmt_check->bind_param("si", $phone_clean, $user_id);
        $stmt_check->execute();
        $check_res = $stmt_check->get_result();
        if ($check_res->num_rows > 0) {
            $_SESSION['error'] = "This phone number is already registered to another account.";
            $stmt_check->close();
            header("Location: profile.php");
            exit;
        }
        $stmt_check->close();
    }

    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $state    = trim($_POST['state'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');

    // Handle Profile Photo Upload
    $has_new_photo = false;
    $new_filename  = "";

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['profile_photo']['tmp_name'];
        $file_name = $_FILES['profile_photo']['name'];
        $file_size = $_FILES['profile_photo']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Validate extension
        if (!in_array($file_ext, $allowed)) {
            $_SESSION['error'] = "Invalid image format. Allowed formats: JPG, JPEG, PNG, GIF, WEBP.";
            header("Location: profile.php");
            exit;
        }

        // Validate file size (max 5MB)
        if ($file_size > 5 * 1024 * 1024) {
            $_SESSION['error'] = "Image size exceeds 5MB limit. Please choose a smaller image.";
            header("Location: profile.php");
            exit;
        }

        // Validate that file is an actual image
        $image_info = @getimagesize($file_tmp);
        if ($image_info === false) {
            $_SESSION['error'] = "Uploaded file is not a valid image.";
            header("Location: profile.php");
            exit;
        }

        $base_dir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        // ── IMPORTANT: Store in uploads/images/ (git-ignored, deploy-safe) ──
        // assets/images/ is wiped on every git deployment — do NOT use it for user files.
        $upload_dir = $base_dir . '/uploads/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $new_filename = 'profile/user_' . $user_id . '_' . time() . '.' . $file_ext;
        $upload_path  = $base_dir . '/uploads/images/' . $new_filename;

        // Ensure the profile sub-directory exists
        $profile_subdir = $base_dir . '/uploads/images/profile';
        if (!is_dir($profile_subdir)) {
            mkdir($profile_subdir, 0755, true);
        }

        if (move_uploaded_file($file_tmp, $upload_path)) {
            $has_new_photo = true;
            // Store as 'uploads/images/profile/user_X_timestamp.ext' in DB
            // resolve_profile_photo_url() checks uploads/ tree and returns correct URL
            $_SESSION['profile_photo'] = $new_filename;
        } else {
            $_SESSION['error'] = "Failed to save uploaded image. Please check server permissions.";
            header("Location: profile.php");
            exit;
        }
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the maximum allowed file size on server.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        $err_code = $_FILES['profile_photo']['error'];
        $_SESSION['error'] = $upload_errors[$err_code] ?? 'An error occurred during file upload.';
        header("Location: profile.php");
        exit;
    }

    if ($has_new_photo) {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, state=?, country=?, zip_code=?, profile_photo=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $name, $phone, $address, $city, $state, $country, $zip_code, $new_filename, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, state=?, country=?, zip_code=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $phone, $address, $city, $state, $country, $zip_code, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        if (isset($_SESSION['needs_profile_update']) && !empty($phone) && !empty($address)) {
            unset($_SESSION['needs_profile_update']);
        }
        $_SESSION['success'] = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update profile: " . $conn->error;
    }
    $stmt->close();

    header("Location: profile.php");
    exit;
}

// ── Fetch fresh user record for display ──
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../includes/auth.php?action=logout");
    exit;
}

// Now include header which renders navigation and HTML structure
include '../includes/header.php';
?>
<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card product-card mb-4">
                <div class="card-body text-center p-4">
                    <?php 
                    $user_photo = !empty($user['profile_photo']) ? $user['profile_photo'] : ($user['google_avatar'] ?? '');
                    $profile_photo_url = resolve_profile_photo_url($user_photo, $user['role'] ?? '');
                    ?>
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($profile_photo_url)): ?>
                            <img id="profileCardImg" src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile" class="rounded-circle object-fit-cover shadow-sm" style="width: 120px; height: 120px; border: 3px solid #007aff;" onerror="this.style.display='none'; document.getElementById('profileCardFallbackIcon').style.display='inline-block';">
                            <i id="profileCardFallbackIcon" class="fas fa-user-circle fa-5x primary-blue" style="display: none;"></i>
                        <?php else: ?>
                            <img id="profileCardImg" src="" alt="Profile" class="rounded-circle object-fit-cover shadow-sm" style="width: 120px; height: 120px; border: 3px solid #007aff; display: none;">
                            <i id="profileCardIcon" class="fas fa-user-circle fa-5x primary-blue"></i>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <?php if (($user['role'] ?? '') === 'retailer'): ?>
                        <div class="mb-2">
                            <span class="badge px-3 py-1 rounded-pill fw-bold" style="background-color: rgba(25, 135, 84, 0.12) !important; color: #198754 !important; border: 1px solid rgba(25, 135, 84, 0.25) !important;">
                                <i class="fas fa-store me-1"></i> Verified Retailer
                            </span>
                        </div>
                    <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                        <div class="mb-2">
                            <span class="badge px-3 py-1 rounded-pill fw-semibold" style="background-color: rgba(13, 110, 253, 0.12) !important; color: #0d6efd !important; border: 1px solid rgba(13, 110, 253, 0.25) !important;">
                                <i class="fas fa-user-shield me-1"></i> Administrator
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mb-2">
                            <span class="badge px-3 py-1 rounded-pill fw-semibold" style="background-color: rgba(108, 117, 125, 0.12) !important; color: #495057 !important; border: 1px solid rgba(108, 117, 125, 0.25) !important;">
                                <i class="fas fa-user me-1"></i> Customer
                            </span>
                        </div>
                    <?php endif; ?>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="small text-muted mb-3"><i class="fas fa-phone me-1"></i> <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'No phone set'; ?></p>
                    <a href="../includes/auth.php?action=logout" class="btn btn-danger btn-custom w-100">Logout</a>
                </div>
            </div>
            <?php if (($user['role'] ?? '') === 'retailer'): ?>
            <div class="card product-card mb-4 border-success border-opacity-25 bg-success bg-opacity-10">
                <div class="card-body p-3 text-start">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fas fa-badge-check text-success fs-5"></i>
                        <h6 class="fw-bold mb-0 text-success">Retailer Account Active</h6>
                    </div>
                    <p class="small text-dark mb-0">You are eligible for bulk wholesale prices on purchases of 12+ units per order.</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="card product-card">
                <div class="card-body p-4">
                    <h5 class="montserrat fw-bold mb-3">Shipping Address</h5>
                    <address class="small text-muted mb-0">
                        <?php if(!empty($user['address'])): ?>
                            <?php echo htmlspecialchars($user['address']); ?><br>
                            <?php echo htmlspecialchars($user['city']); ?>, <?php echo htmlspecialchars($user['state']); ?> <?php echo htmlspecialchars($user['zip_code']); ?><br>
                            <?php echo htmlspecialchars($user['country']); ?>
                        <?php else: ?>
                            <em>No address information saved. Please update your profile.</em>
                        <?php endif; ?>
                    </address>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card product-card">
                <div class="card-body p-4">
                    <h4 class="montserrat primary-blue mb-4">Edit Profile</h4>
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" id="profileForm" autocomplete="on">
                        <?php echo csrf_field(); ?>
                        <div class="mb-4">
                            <label for="profile_photo" class="form-label fw-semibold">Profile Photo</label>
                            <input class="form-control" type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text text-muted">Upload JPG, PNG, WEBP, or GIF image (Max 5MB).</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="profile_name">Full Name</label>
                                <input type="text" name="name" id="profile_name" class="form-control" autocomplete="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile_email">Email</label>
                                <input type="email" id="profile_email" class="form-control" autocomplete="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="profile_phone">Phone Number</label>
                                <?php echo render_phone_input('phone', $user['phone'] ?? '', true); ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile_zip">Zip / Pincode</label>
                                <input type="text" name="zip_code" id="profile_zip" class="form-control" autocomplete="postal-code" inputmode="numeric" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile_address">Address</label>
                            <input type="text" name="address" id="profile_address" class="form-control" autocomplete="street-address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile_city">City</label>
                            <input type="text" name="city" id="profile_city" class="form-control" autocomplete="address-level2" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="profile_state">State/Province</label>
                                <input type="text" name="state" id="profile_state" class="form-control" autocomplete="address-level1" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile_country">Country</label>
                                <input type="text" name="country" id="profile_country" class="form-control" autocomplete="country-name" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-custom px-4 mt-2">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Instant Photo Preview & Smart Profile: Autofill Detection & AJAX Auto-Save -->
<script>
(function() {
    'use strict';

    var CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
    var SAVE_URL = 'ajax_update_profile.php';

    // ── Live Instant Image Preview ──
    var photoInput = document.getElementById('profile_photo');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            var file = e.target.files && e.target.files[0];
            if (file) {
                var img = document.getElementById('profileCardImg');
                var icon = document.getElementById('profileCardIcon');
                var fallback = document.getElementById('profileCardFallbackIcon');
                if (fallback) fallback.style.display = 'none';
                if (icon) icon.style.display = 'none';
                if (img) {
                    img.src = URL.createObjectURL(file);
                    img.style.display = 'inline-block';
                }
            }
        });
    }

    // Track initial server values to avoid saving unchanged data
    var initialValues = {};
    var FIELDS = [
        {id: 'profile_name',    key: 'name'},
        {id: 'profile_address', key: 'address'},
        {id: 'profile_city',    key: 'city'},
        {id: 'profile_state',   key: 'state'},
        {id: 'profile_country', key: 'country'},
        {id: 'profile_zip',     key: 'zip_code'}
    ];

    document.addEventListener('DOMContentLoaded', function() {
        // Capture initial values
        FIELDS.forEach(function(f) {
            var el = document.getElementById(f.id);
            if (el) initialValues[f.key] = el.value.trim();
        });
        var phoneEl = document.querySelector('#profileForm .phone-hidden-final');
        if (phoneEl) initialValues['phone'] = phoneEl.value.trim();

        // ── Autofill Detection via Polling ──
        var autofillDetected = false;
        var pollCount = 0;
        var pollInterval = setInterval(function() {
            pollCount++;
            if (pollCount > 30 || autofillDetected) { // Stop after 3 seconds
                clearInterval(pollInterval);
                return;
            }

            FIELDS.forEach(function(f) {
                var el = document.getElementById(f.id);
                if (!el) return;
                var newVal = el.value.trim();
                if (newVal && newVal !== initialValues[f.key]) {
                    autofillDetected = true;
                }
            });

            if (autofillDetected) {
                clearInterval(pollInterval);
                debouncedSave();
            }
        }, 100);

        // ── Debounced Auto-Save ──
        var saveTimer = null;
        function debouncedSave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(doAutoSave, 1500);
        }

        function doAutoSave() {
            var data = new FormData();
            data.append('csrf_token', CSRF_TOKEN);
            var hasChanges = false;

            FIELDS.forEach(function(f) {
                var el = document.getElementById(f.id);
                if (!el) return;
                var val = el.value.trim();
                if (val && val !== initialValues[f.key]) {
                    data.append(f.key, val);
                    hasChanges = true;
                }
            });

            // Phone
            var phoneHidden = document.querySelector('#profileForm .phone-hidden-final');
            if (phoneHidden) {
                var phoneVal = phoneHidden.value.trim();
                if (phoneVal && phoneVal !== initialValues['phone']) {
                    data.append('phone', phoneVal);
                    hasChanges = true;
                }
            }

            if (!hasChanges) return;

            fetch(SAVE_URL, {
                method: 'POST',
                body: data
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success && resp.updated_count > 0) {
                    showAutoSaveToast('Details detected and saved automatically');
                    // Update initial values so we don't re-save
                    FIELDS.forEach(function(f) {
                        var el = document.getElementById(f.id);
                        if (el) initialValues[f.key] = el.value.trim();
                    });
                    if (phoneHidden) initialValues['phone'] = phoneHidden.value.trim();
                }
            })
            .catch(function() { /* silent fail for auto-save */ });
        }

        // ── Listen for manual input/change events ──
        FIELDS.forEach(function(f) {
            var el = document.getElementById(f.id);
            if (el) {
                el.addEventListener('input', debouncedSave);
                el.addEventListener('change', debouncedSave);
            }
        });

        // Phone field change
        var phoneMainInput = document.querySelector('#profileForm .phone-main-input');
        if (phoneMainInput) {
            phoneMainInput.addEventListener('input', debouncedSave);
            phoneMainInput.addEventListener('change', debouncedSave);
        }
        var phoneCodeSelect = document.querySelector('#profileForm .country-code-select');
        if (phoneCodeSelect) {
            phoneCodeSelect.addEventListener('change', debouncedSave);
        }

        // ── Toast Notification ──
        function showAutoSaveToast(msg) {
            var existing = document.getElementById('autosaveToast');
            if (existing) existing.remove();

            var toast = document.createElement('div');
            toast.id = 'autosaveToast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:0.9rem;font-weight:600;color:#2e7d32;background:linear-gradient(135deg,#e8f5e9,#f1f8e9);box-shadow:0 4px 16px rgba(0,0,0,0.12);display:flex;align-items:center;gap:8px;animation:slideInToast 0.4s ease;';
            toast.innerHTML = '<i class="fas fa-check-circle"></i> ' + msg;
            document.body.appendChild(toast);

            // Add animation keyframes if not present
            if (!document.getElementById('toastAnimStyle')) {
                var style = document.createElement('style');
                style.id = 'toastAnimStyle';
                style.textContent = '@keyframes slideInToast{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
                document.head.appendChild(style);
            }

            setTimeout(function() {
                toast.style.transition = 'opacity 0.4s ease';
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 400);
            }, 3500);
        }
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
