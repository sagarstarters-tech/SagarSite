<?php
include '../includes/header.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: profile.php");
        exit;
    }

    $name = $conn->real_escape_string($_POST['name']);
    
    $phone_raw = trim($_POST['phone'] ?? '');
    $phone = $conn->real_escape_string($phone_raw);
    $phone_clean = str_replace([' ', '-', '(', ')', '+'], '', $phone_raw);
    $phone_clean_sql = $conn->real_escape_string($phone_clean);

    if (strlen($phone_clean) > 5) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')=? AND id != ?");
        $stmt_check->bind_param("si", $phone_clean_sql, $user_id);
        $stmt_check->execute();
        $check_res = $stmt_check->get_result();
        if ($check_res->num_rows > 0) {
            $_SESSION['error'] = "This phone number is already registered to another account.";
            header("Location: profile.php");
            exit;
        }
        $stmt_check->close();
    }

    $address = $conn->real_escape_string($_POST['address']);
    $city = $conn->real_escape_string($_POST['city']);
    $state = $conn->real_escape_string($_POST['state']);
    $country = $conn->real_escape_string($_POST['country']);
    $zip_code = $conn->real_escape_string($_POST['zip_code']);
    
    // Handle Profile Photo Upload
    $has_new_photo = false;
    $new_filename = "";
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_photo']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed)) {
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = '../assets/images/' . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                $has_new_photo = true;
                $_SESSION['profile_photo'] = $new_filename;
                $user['profile_photo'] = $new_filename;
            }
        }
    }
    
    if ($has_new_photo) {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, state=?, country=?, zip_code=?, profile_photo=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $name, $phone, $address, $city, $state, $country, $zip_code, $new_filename, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, state=?, country=?, zip_code=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $phone, $address, $city, $state, $country, $zip_code, $user_id);
    }
    $stmt->execute();
    $stmt->close();
    $_SESSION['name'] = $name;
    if (isset($_SESSION['needs_profile_update'])) {
        unset($_SESSION['needs_profile_update']);
    }
    $success = "Profile updated successfully.";
    $user['name'] = $name;
    $user['phone'] = $phone;
    $user['address'] = $address;
    $user['city'] = $city;
    $user['state'] = $state;
    $user['country'] = $country;
    $user['zip_code'] = $zip_code;
}
?>
<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card product-card mb-4">
                <div class="card-body text-center p-4">
                    <?php 
                    $profile_photo_url = resolve_profile_photo_url($user['profile_photo'] ?? '', $user['role'] ?? '');
                    ?>
                    <?php if (!empty($profile_photo_url)): ?>
                        <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile" class="rounded-circle mb-3 object-fit-cover" style="width: 120px; height: 120px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <i class="fas fa-user-circle fa-5x primary-blue mb-3"></i>
                    <?php endif; ?>
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <?php if (($user['role'] ?? '') === 'retailer'): ?>
                        <div class="mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1 rounded-pill fw-bold">
                                <i class="fas fa-store me-1"></i> Verified Retailer
                            </span>
                        </div>
                    <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                        <div class="mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 rounded-pill">
                                <i class="fas fa-user-shield me-1"></i> Administrator
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mb-2">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1 rounded-pill">
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
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <?php if(isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" id="profileForm" autocomplete="on">
                        <?php echo csrf_field(); ?>
                        <div class="mb-4 text-center">
                            <label for="profile_photo" class="form-label d-block text-start">Profile Photo</label>
                            <input class="form-control" type="file" name="profile_photo" id="profile_photo" accept="image/*">
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

<!-- Smart Profile: Autofill Detection & AJAX Auto-Save -->
<script>
(function() {
    'use strict';

    var CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
    var SAVE_URL = 'ajax_update_profile.php';

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
        // Chrome applies :-webkit-autofill pseudo-class with a special background
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
