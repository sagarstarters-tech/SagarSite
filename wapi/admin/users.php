<?php
/**
 * WAPI SaaS - Admin Users Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$settings = new Settings();

$hideNav = true; // Prevents landing page nav from appearing in admin

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';
    $userId = sanitizeInt($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $userId) {
        $user = $db->fetch("SELECT status FROM users WHERE id = ? AND role = 'user'", [$userId]);
        if ($user) {
            $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
            $db->update('users', ['status' => $newStatus], 'id = ?', [$userId]);
            setFlash('success', 'User status updated successfully.');
        }
    } elseif ($action === 'delete' && $userId) {
        $db->delete('users', "id = ? AND role = 'user'", [$userId]);
        setFlash('success', 'User deleted successfully.');
    } elseif ($action === 'add_user') {
        $name = sanitize($_POST['name']);
        $email = sanitizeEmail($_POST['email']);
        $password = trim($_POST['password']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Invalid email format.');
        } elseif (strlen($password) < 8) {
            setFlash('danger', 'Password must be at least 8 characters.');
        } elseif ($db->fetch("SELECT id FROM users WHERE email = ?", [$email])) {
            setFlash('danger', 'Email already exists.');
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $planId = sanitizeInt($_POST['plan_id'] ?? 0);
            
            $db->beginTransaction();
            try {
                $newUserId = $db->insert('users', [
                    'uuid' => generateUUID(),
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'user',
                    'status' => 'active',
                    'email_verified' => 1
                ]);
                
                if ($newUserId) {
                    $initialCredits = 0;
                    if ($planId > 0) {
                        $plan = $db->fetch("SELECT * FROM plans WHERE id = ?", [$planId]);
                        if ($plan) {
                            $initialCredits = $plan['message_limit'];
                            $startsAt = date('Y-m-d H:i:s');
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
                            
                            $db->insert('subscriptions', [
                                'user_id' => $newUserId,
                                'plan_id' => $planId,
                                'billing_cycle' => 'monthly',
                                'amount' => 0, // Manual addition
                                'status' => 'active',
                                'starts_at' => $startsAt,
                                'expires_at' => $expiresAt
                            ]);
                        }
                    }

                    $db->insert('credits', [
                        'user_id' => $newUserId,
                        'total_credits' => $initialCredits ?: 100,
                        'used_credits' => 0
                    ]);
                    
                    $db->commit();
                    setFlash('success', 'User added successfully' . ($planId ? ' with ' . $plan['name'] . ' plan.' : '.'));
                } else {
                    throw new Exception('Error inserting user.');
                }
            } catch (Exception $e) {
                $db->rollback();
                setFlash('danger', 'Error adding user: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit_user' && $userId) {
        $name = sanitize($_POST['name']);
        $email = sanitizeEmail($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $status = sanitize($_POST['status']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Invalid email format.');
        } else {
            $exists = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($exists) {
                setFlash('danger', 'Email already exists for another user.');
            } else {
                $planId = sanitizeInt($_POST['plan_id'] ?? 0);
                
                $db->beginTransaction();
                try {
                    // Update basic details
                    $db->update('users', [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'status' => $status
                    ], 'id = ?', [$userId]);

                    // Handle Credits update safely
                    $addCredits = sanitizeInt($_POST['add_credits'] ?? 0);
                    if ($addCredits > 0) {
                        $db->query("UPDATE credits SET total_credits = total_credits + ? WHERE user_id = ?", [$addCredits, $userId]);
                        
                        // Log credit transaction
                        $currentCredits = $db->fetch("SELECT total_credits, used_credits FROM credits WHERE user_id = ?", [$userId]);
                        $balance = $currentCredits ? ($currentCredits['total_credits'] - $currentCredits['used_credits']) : 0;
                        
                        $db->insert('credit_transactions', [
                            'user_id' => $userId,
                            'type' => 'credit',
                            'amount' => $addCredits,
                            'balance_after' => $balance,
                            'description' => 'Added by Admin',
                            'reference_id' => $_SESSION['user_id'] // Admin ID
                        ]);
                    }

                    // Handle Plan update
                    $currentActiveSubscription = $db->fetch("SELECT plan_id, id FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1", [$userId]);
                    $currentPlanId = $currentActiveSubscription ? (int)$currentActiveSubscription['plan_id'] : 0;

                    if ($planId !== $currentPlanId) {
                        // Cancel existing
                        if ($currentActiveSubscription) {
                            $db->update('subscriptions', ['status' => 'cancelled'], 'id = ?', [$currentActiveSubscription['id']]);
                        }

                        // Add new if not "No Plan"
                        if ($planId > 0) {
                            $plan = $db->fetch("SELECT * FROM plans WHERE id = ?", [$planId]);
                            if ($plan) {
                                $startsAt = date('Y-m-d H:i:s');
                                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
                                
                                $db->insert('subscriptions', [
                                    'user_id' => $userId,
                                    'plan_id' => $planId,
                                    'billing_cycle' => 'monthly',
                                    'amount' => 0,
                                    'status' => 'active',
                                    'starts_at' => $startsAt,
                                    'expires_at' => $expiresAt
                                ]);

                                // Reset/Update credits to new plan limit
                                $db->update('credits', [
                                    'total_credits' => $plan['message_limit'],
                                    'used_credits' => 0
                                ], 'user_id = ?', [$userId]);
                            }
                        } else {
                            // If set to "No Plan", optionally reset credits or keep them. 
                            // Usually, "No Plan" means no access.
                            $db->update('credits', ['total_credits' => 0, 'used_credits' => 0], 'user_id = ?', [$userId]);
                        }
                    }

                    $db->commit();
                    setFlash('success', 'User updated successfully.');
                } catch (Exception $e) {
                    $db->rollback();
                    setFlash('danger', 'Error updating user: ' . $e->getMessage());
                }
            }
        }
    }
    redirect('admin/users.php');
}

// Search & Filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = "role = 'user'";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalUsers = $db->count('users', $where, $params);
$pagination = paginate($totalUsers, $page);
$users = $db->fetchAll("SELECT u.*, 
    (SELECT plan_id FROM subscriptions WHERE user_id = u.id AND status = 'active' ORDER BY created_at DESC LIMIT 1) as active_plan,
    c.total_credits, c.used_credits
    FROM users u 
    LEFT JOIN credits c ON u.id = c.user_id 
    WHERE {$where} ORDER BY u.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

// Get plan names and add to users array
$plans = $db->fetchAll("SELECT id, name, message_limit FROM plans WHERE is_active = 1 ORDER BY sort_order");
$planMap = [];
foreach ($plans as $p) $planMap[$p['id']] = $p['name'];

foreach ($users as &$u) {
    $u['plan_name'] = isset($u['active_plan']) ? ($planMap[$u['active_plan']] ?? 'Unknown') : 'No Plan';
}
unset($u);

$pageTitle = 'User Management';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Users</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('admin/'); ?>">Admin</a><i class="bi bi-chevron-right"></i><span>Users</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill"></i> Add User
                </button>
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?> fade-in"><i class="bi bi-<?= $flash['type'] === 'success' ? 'check' : 'exclamation'; ?>-circle-fill"></i> <?= e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="data-table">
            <div class="data-table-header">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="data-table-title mb-0">All Users (<?= $totalUsers; ?>)</h5>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <form class="d-flex gap-2" method="GET">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= e($search); ?>">
                        </div>
                        <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No users found</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)); ?></div>
                                    <div>
                                        <div class="fw-bold"><?= e($user['name']); ?></div>
                                        <div style="font-size: 0.8125rem; color: var(--text-muted);"><?= e($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= e($user['plan_name']); ?></span></td>
                            <td><span class="status-badge status-<?= $user['status']; ?>"><?= ucfirst($user['status']); ?></span></td>
                            <td style="font-size: 0.875rem;"><?= formatDate($user['created_at']); ?></td>
                            <td style="font-size: 0.875rem;"><?= $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);" 
                                            title="View Details" onclick='viewUser(<?= json_encode($user); ?>)'>
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);" 
                                            title="Edit Details" onclick='editUser(<?= json_encode($user); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                        <button class="btn btn-icon btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color);" title="<?= $user['status'] === 'active' ? 'Suspend' : 'Activate'; ?>">
                                            <i class="bi bi-<?= $user['status'] === 'active' ? 'pause-circle' : 'play-circle'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                                        <?= CSRF::tokenField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                        <button class="btn btn-icon btn-sm" style="background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2);" title="Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&page=%d'); ?></div>
        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="John Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Assign Plan</label>
                        <select name="plan_id" class="form-control">
                            <option value="0">No Plan (Manual)</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id']; ?>"><?= e($p['name']); ?> (<?= $p['message_limit']; ?> msgs)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">If selected, a 1-month active subscription will be created.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <?= CSRF::tokenField(); ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Active Plan</label>
                        <select name="plan_id" id="edit_plan_id" class="form-control">
                            <option value="0">No Plan (Manual)</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id']; ?>"><?= e($p['name']); ?> (<?= $p['message_limit']; ?> msgs)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Changing plan will reset user credits to the new plan's limit.</div>
                    </div>

                    <div class="row g-3 mt-1 border-top pt-3">
                        <div class="col-6">
                            <label class="form-label text-muted" style="font-size: 0.75rem;">Current Total Credits</label>
                            <input type="text" id="edit_total_credits" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted" style="font-size: 0.75rem;">Used Credits</label>
                            <input type="text" id="edit_used_credits" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="col-12 mt-2">
                            <label class="form-label fw-bold text-success">Add New Credits</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white"><i class="bi bi-plus-lg"></i></span>
                                <input type="number" name="add_credits" class="form-control" placeholder="0" min="0">
                            </div>
                            <div class="form-text">Enter the amount of credits to ADD to the current total.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4 text-center border-bottom bg-light">
                    <div class="user-avatar mx-auto mb-3" id="view_avatar" style="width: 64px; height: 64px; font-size: 1.5rem;">U</div>
                    <h5 class="fw-bold mb-1" id="view_name">John Doe</h5>
                    <p class="text-muted mb-0" id="view_email">john@example.com</p>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">Plan</div>
                            <div class="fw-bold" id="view_plan_name">-</div>
                        </div>
                        <div class="col-6">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">Status</div>
                            <div id="view_status_badge">-</div>
                        </div>
                        <div class="col-6">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">Role</div>
                            <div class="fw-bold" id="view_role">-</div>
                        </div>
                        <div class="col-6">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">Phone</div>
                            <div class="fw-bold" id="view_phone">-</div>
                        </div>
                        <div class="col-6">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">Joined On</div>
                            <div class="fw-bold" id="view_joined">-</div>
                        </div>
                        <div class="col-12">
                            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px;">User UUID</div>
                            <div class="fw-bold" style="font-size: 0.8125rem; font-family: monospace;" id="view_uuid">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewUser(user) {
    document.getElementById('view_name').innerText = user.name;
    document.getElementById('view_email').innerText = user.email;
    document.getElementById('view_phone').innerText = user.phone || 'N/A';
    document.getElementById('view_uuid').innerText = user.uuid;
    document.getElementById('view_role').innerText = user.role.toUpperCase();
    document.getElementById('view_joined').innerText = user.created_at;
    document.getElementById('view_avatar').innerText = user.name.charAt(0).toUpperCase();
    document.getElementById('view_plan_name').innerText = user.plan_name;
    
    const badge = document.getElementById('view_status_badge');
    badge.innerHTML = `<span class="status-badge status-${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span>`;
    
    new bootstrap.Modal(document.getElementById('viewUserModal')).show();
}

function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_plan_id').value = user.active_plan || 0;
    document.getElementById('edit_total_credits').value = user.total_credits || 0;
    document.getElementById('edit_used_credits').value = user.used_credits || 0;
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
