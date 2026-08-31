<?php
include 'admin_header.php';

// Auto-migrate: Ensure retailer role is supported in enum
$check_role_enum = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role_enum && $r_enum = $check_role_enum->fetch_assoc()) {
    if (strpos($r_enum['Type'], 'retailer') === false) {
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','retailer') DEFAULT 'user'");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id != $_SESSION['user_id']) { // Don't delete self
            $conn->query("DELETE FROM users WHERE id=$id AND role!='admin'");
            $_SESSION['flash_success'] = "User deleted successfully.";
        } else {
            $_SESSION['flash_error'] = "You cannot delete your own admin account.";
        }
        header("Location: manage_users.php");
        exit;
    } elseif ($action === 'edit_user') {
        $id = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $address = $conn->real_escape_string($_POST['address']);
        $city = $conn->real_escape_string($_POST['city']);
        $state = $conn->real_escape_string($_POST['state']);
        $country = $conn->real_escape_string($_POST['country']);
        $zip_code = $conn->real_escape_string($_POST['zip_code']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // Prevent admin from demoting themselves or deleting their own rights accidentally
        if ($id == $_SESSION['user_id'] && $role !== 'admin') {
             $error = "You cannot demote yourself from admin.";
        } else {
             // Check if email already exists for another user
             $check = $conn->query("SELECT id FROM users WHERE email='$email' AND id != $id");
             if ($check->num_rows > 0) {
                 $error = "This email is already associated with another account.";
             } else {
                 $password_update = "";
                 if (!empty($_POST['password'])) {
                     $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                     $password_update = ", password='$hashed_password'";
                 }
                 
                 $photo_update = "";
                 if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                     $tmp_name = $_FILES['profile_photo']['tmp_name'];
                     $file_name = basename($_FILES['profile_photo']['name']);
                     $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                     $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                     
                     if (in_array($ext, $allowed)) {
                         $new_name = 'profile/user_' . $id . '_' . time() . '.' . $ext;
                         $upload_dir = '../uploads/images/profile/';
                         if (!is_dir($upload_dir)) {
                             mkdir($upload_dir, 0755, true);
                         }
                         if (move_uploaded_file($tmp_name, '../uploads/images/' . $new_name)) {
                             $safe_name = $conn->real_escape_string($new_name);
                             $photo_update = ", profile_photo='$safe_name'";
                         }
                     }
                 }
                 
                 $conn->query("UPDATE users SET name='$name', email='$email' $password_update $photo_update, phone='$phone', address='$address', city='$city', state='$state', country='$country', zip_code='$zip_code', role='$role' WHERE id=$id");
                 $_SESSION['flash_success'] = "User #$id updated successfully.";
                 header("Location: manage_users.php");
                 exit;
             }
        }
    } elseif ($action === 'add_user') {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $address = $conn->real_escape_string($_POST['address'] ?? '');
        $city = $conn->real_escape_string($_POST['city'] ?? '');
        $state = $conn->real_escape_string($_POST['state'] ?? '');
        $country = $conn->real_escape_string($_POST['country'] ?? '');
        $zip_code = $conn->real_escape_string($_POST['zip_code'] ?? '');
        $role = $conn->real_escape_string($_POST['role']);
        
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $_SESSION['flash_error'] = "Email already exists!";
        } else {
            $sql = "INSERT INTO users (name, email, password, phone, address, city, state, country, zip_code, role) VALUES ('$name', '$email', '$password', '$phone', '$address', '$city', '$state', '$country', '$zip_code', '$role')";
            if ($conn->query($sql)) {
                $_SESSION['flash_success'] = "User added successfully.";
            } else {
                $_SESSION['flash_error'] = "Error adding user: " . $conn->error;
            }
        }
        header("Location: manage_users.php");
        exit;
    }
}

// Flash messages
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$retailers_count = (int)($conn->query("SELECT COUNT(*) as c FROM users WHERE role='retailer'")->fetch_assoc()['c'] ?? 0);
$total_pages = ceil($total_users / $limit);
?>

<style>
.mu-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
}
.mu-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.mu-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    flex-shrink: 0;
}
.mu-avatar-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>

<div class="container-fluid py-3">

    <!-- Hero Header Banner -->
    <div class="mu-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-users me-1"></i> User Accounts
                    </span>
                    <?php if($retailers_count > 0): ?>
                    <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-store me-1"></i> <?php echo $retailers_count; ?> Retailers
                    </span>
                    <?php endif; ?>
                    <span class="text-white-50 small"><?php echo $total_users; ?> registered users</span>
                </div>
                <h3 class="fw-bold mb-0 text-white">Customer & User Management</h3>
            </div>
            <div>
                <button class="btn btn-primary px-3 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center gap-2" data-mdb-toggle="modal" data-mdb-target="#addUserModal">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New User</span>
                </button>
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success rounded-3 py-2 px-3 mb-3 shadow-sm"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3 mb-3 shadow-sm"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Table Container -->
    <div class="mu-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">User Details</th>
                        <th>Email & Phone</th>
                        <th>Shipping Address</th>
                        <th>Account Role</th>
                        <th>Joined Date</th>
                        <th class="pe-4 text-end">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($users && $users->num_rows > 0): ?>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <?php
                            $uInitials = strtoupper(substr($u['name'], 0, 2));
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php 
                                        $uPhotoUrl = resolve_profile_photo_url($u['profile_photo'] ?? '', $u['role'] ?? '');
                                    ?>
                                    <?php if(!empty($uPhotoUrl)): ?>
                                        <img src="<?php echo htmlspecialchars($uPhotoUrl); ?>" alt="Avatar" class="mu-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="mu-avatar-placeholder" style="display:none;"><?php echo $uInitials; ?></div>
                                    <?php else: ?>
                                        <div class="mu-avatar-placeholder"><?php echo $uInitials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <small class="text-muted">ID: #<?php echo $u['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($u['email']); ?></div>
                                <small class="text-muted d-block"><i class="fas fa-phone me-1 text-primary"></i> <?php echo !empty($u['phone']) ? htmlspecialchars($u['phone']) : 'N/A'; ?></small>
                            </td>
                            <td>
                                <?php if(!empty($u['address'])): ?>
                                    <small class="text-dark d-block"><?php echo htmlspecialchars($u['address']); ?></small>
                                    <small class="text-muted"><?php echo htmlspecialchars($u['city']); ?>, <?php echo htmlspecialchars($u['state']); ?> <?php echo htmlspecialchars($u['zip_code']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">No address provided</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['role'] === 'admin'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="fas fa-user-shield me-1"></i> Admin</span>
                                <?php elseif($u['role'] === 'retailer'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-bold"><i class="fas fa-store me-1"></i> Retailer</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="fas fa-user me-1"></i> Customer</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-muted fw-semibold"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button class="btn btn-primary btn-sm rounded-3 px-2 py-1 edit-user-btn" 
                                        data-id="<?php echo $u['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($u['name']); ?>"
                                        data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($u['phone']); ?>"
                                        data-role="<?php echo $u['role']; ?>"
                                        data-address="<?php echo htmlspecialchars($u['address']); ?>"
                                        data-city="<?php echo htmlspecialchars($u['city']); ?>"
                                        data-state="<?php echo htmlspecialchars($u['state']); ?>"
                                        data-country="<?php echo htmlspecialchars($u['country']); ?>"
                                        data-zip="<?php echo htmlspecialchars($u['zip_code']); ?>"
                                        title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($u['role'] !== 'admin'): ?>
                                    <form method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Delete this user? This will also remove their orders.');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm rounded-3 px-2 py-1" title="Delete User"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-secondary btn-sm rounded-3 px-2 py-1 disabled" title="Cannot delete admins"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="p-3 border-top bg-light">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="modal-title fw-bold montserrat">Add New User</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwAddUser">
                                <label class="form-check-label small text-muted" for="showPwAddUser">Show password</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user">Customer</option>
                                <option value="retailer">Retailer (Wholesale Buyer)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <?php echo render_phone_input('phone', '', true); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zip Code</label>
                            <input type="text" name="zip_code" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="modal-title fw-bold montserrat">Edit User <span class="text-primary" id="editUserIdTitle"></span></h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <small class="text-muted">(Login ID)</small></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password <small class="text-danger">(Leave blank to keep current)</small></label>
                            <input type="password" name="password" id="edit_password" class="form-control" placeholder="Enter new password">
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwEditUser">
                                <label class="form-check-label small text-muted" for="showPwEditUser">Show password</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <?php echo render_phone_input('phone', '', true, '', 'edit_phone_group'); ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="user">Customer</option>
                                <option value="retailer">Retailer (Wholesale Buyer)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profile Image <small class="text-muted">(Optional)</small></label>
                            <input type="file" name="profile_photo" id="edit_profile_photo" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="edit_address" class="form-control">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="edit_city" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" name="state" id="edit_state" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="edit_country" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zip Code</label>
                            <input type="text" name="zip_code" id="edit_zip" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-user-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('editUserIdTitle').textContent = '#' + this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_email').value = this.dataset.email;
            
            // Handle Phone and Country Code for Edit Modal
            const phone = this.dataset.phone;
            const phoneGroup = document.querySelector('.edit_phone_group');
            const select = phoneGroup.querySelector('.country-code-select');
            const input = phoneGroup.querySelector('.phone-main-input');
            const hidden = phoneGroup.querySelector('.phone-hidden-final');
            
            // Reset to default
            select.selectedIndex = 0;
            input.value = phone;
            
            // Try to match code
            const options = select.options;
            for(let i=0; i<options.length; i++) {
                if(phone.startsWith(options[i].value)) {
                    select.value = options[i].value;
                    input.value = phone.substring(options[i].value.length);
                    break;
                }
            }
            hidden.value = phone;

            document.getElementById('edit_role').value = this.dataset.role;
            document.getElementById('edit_address').value = this.dataset.address;
            document.getElementById('edit_city').value = this.dataset.city;
            document.getElementById('edit_state').value = this.dataset.state;
            document.getElementById('edit_country').value = this.dataset.country;
            document.getElementById('edit_zip').value = this.dataset.zip;
            
            var modal = new mdb.Modal(document.getElementById('editUserModal'));
            modal.show();
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
