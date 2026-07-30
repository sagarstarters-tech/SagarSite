<?php
include 'admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id != $_SESSION['user_id']) { // Don't delete self
            $conn->query("DELETE FROM users WHERE id=$id AND role='user'");
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
        if ($id == $_SESSION['user_id'] && $role == 'user') {
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
                         $new_name = uniqid('profile_') . '.' . $ext;
                         $upload_dir = '../assets/images/';
                         if (!is_dir($upload_dir)) {
                             mkdir($upload_dir, 0777, true);
                         }
                         if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                             $photo_update = ", profile_photo='$new_name'";
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
$total_pages = ceil($total_users / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manage Users</h4>
    <button class="btn btn-primary btn-custom" data-mdb-toggle="modal" data-mdb-target="#addUserModal">
        <i class="fas fa-plus me-2"></i>Add User
    </button>
</div>

<?php if(isset($success)): ?>
    <div class="alert alert-success py-2"><?php echo $success; ?></div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert alert-danger py-2"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Name</th>
                        <th>Email & Phone</th>
                        <th>Address</th>
                        <th>Role</th>
                        <th>Joined Date</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($users && $users->num_rows > 0): ?>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $u['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if(!empty($u['profile_photo'])): ?>
                                        <img src="../assets/images/<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="Avatar" class="rounded-circle me-3 object-fit-cover" style="width: 40px; height: 40px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                    <?php endif; ?>
                                    <span class="fw-bold"><?php echo htmlspecialchars($u['name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($u['email']); ?></div>
                                <small class="text-muted"><i class="fas fa-phone me-1"></i> <?php echo !empty($u['phone']) ? htmlspecialchars($u['phone']) : 'N/A'; ?></small>
                            </td>
                            <td>
                                <?php if(!empty($u['address'])): ?>
                                    <small><?php echo htmlspecialchars($u['address']); ?>, <br><?php echo htmlspecialchars($u['city']); ?>, <?php echo htmlspecialchars($u['state']); ?> <?php echo htmlspecialchars($u['zip_code']); ?><br><?php echo htmlspecialchars($u['country']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['role'] === 'admin'): ?>
                                    <span class="badge bg-primary text-white px-2 py-1">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-2 py-1">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td class="pe-4 text-end">
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm btn-custom edit-user-btn" 
                                        data-id="<?php echo $u['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($u['name']); ?>"
                                        data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($u['phone']); ?>"
                                        data-role="<?php echo $u['role']; ?>"
                                        data-address="<?php echo htmlspecialchars($u['address']); ?>"
                                        data-city="<?php echo htmlspecialchars($u['city']); ?>"
                                        data-state="<?php echo htmlspecialchars($u['state']); ?>"
                                        data-country="<?php echo htmlspecialchars($u['country']); ?>"
                                        data-zip="<?php echo htmlspecialchars($u['zip_code']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($u['role'] === 'user'): ?>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this user? This will also remove their orders.');">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-secondary btn-sm btn-custom disabled px-3" title="Cannot delete admins"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="p-3 border-top">
            <nav>
                <ul class="pagination justify-content-center mb-0">
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
                                <option value="user">User</option>
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
                                <option value="user">User</option>
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
