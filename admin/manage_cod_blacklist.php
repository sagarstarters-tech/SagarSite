<?php
/**
 * ============================================================
 *  COD Blacklist Manager — Admin Panel
 *  Location: /admin/manage_cod_blacklist.php
 * ============================================================
 */
include 'admin_header.php';
require_once __DIR__ . '/../includes/CodService.php';

// Ensure auto-migration runs
$codService = new CodService($conn, $global_settings);

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_blacklist') {
        $type   = $conn->real_escape_string($_POST['bl_type'] ?? '');
        $value  = trim($conn->real_escape_string($_POST['bl_value'] ?? ''));
        $reason = trim($conn->real_escape_string($_POST['bl_reason'] ?? ''));

        if (!in_array($type, ['phone', 'email', 'ip'])) {
            $error = "Invalid blacklist type.";
        } elseif (empty($value)) {
            $error = "Value cannot be empty.";
        } else {
            // Normalise phone
            if ($type === 'phone') {
                $value = preg_replace('/[^0-9]/', '', $value);
                if (strlen($value) > 10) {
                    $value = substr($value, -10);
                }
            } elseif ($type === 'email') {
                $value = strtolower($value);
            }

            $stmt = $conn->prepare("INSERT INTO cod_blacklist (type, value, reason) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
            $stmt->bind_param("sss", $type, $value, $reason);
            if ($stmt->execute()) {
                $success = "Entry added/updated successfully.";
            } else {
                $error = "Failed to add: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_blacklist') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM cod_blacklist WHERE id = $id");
            $success = "Entry removed.";
        }
    }
}

// Fetch all entries
$search = trim($_GET['search'] ?? '');
$filter_type = $_GET['filter_type'] ?? '';

$where = "1=1";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (value LIKE '%$s%' OR reason LIKE '%$s%')";
}
if (!empty($filter_type) && in_array($filter_type, ['phone', 'email', 'ip'])) {
    $where .= " AND type = '$filter_type'";
}

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$total_q = $conn->query("SELECT COUNT(*) as c FROM cod_blacklist WHERE $where");
$total_entries = $total_q ? $total_q->fetch_assoc()['c'] : 0;
$total_pages = ceil($total_entries / $limit);

$entries = $conn->query("SELECT * FROM cod_blacklist WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Stats
$stats_phone = $conn->query("SELECT COUNT(*) as c FROM cod_blacklist WHERE type='phone'")->fetch_assoc()['c'];
$stats_email = $conn->query("SELECT COUNT(*) as c FROM cod_blacklist WHERE type='email'")->fetch_assoc()['c'];
$stats_ip    = $conn->query("SELECT COUNT(*) as c FROM cod_blacklist WHERE type='ip'")->fetch_assoc()['c'];
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <!-- Hero / Header Banner -->
    <div class="adm-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="adm-hero-badge">
                        <i class="fas fa-shield-alt"></i> Fraud Prevention &amp; Risk Control
                    </span>
                    <span class="adm-hero-badge badge-warning">
                        <i class="fas fa-ban"></i> Active Restrictions: <?php echo number_format($total_entries); ?>
                    </span>
                </div>
                <h2 class="adm-hero-title">COD Blacklist Manager 🛡️</h2>
                <p class="adm-hero-subtitle">Restrict Cash on Delivery for high-risk users by phone number, email address, or network IP.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="manage_settings.php?tab=payment" class="btn adm-btn-white">
                    <i class="fas fa-wallet text-primary"></i>
                    <span>Payment Gateways</span>
                </a>
                <button class="btn btn-primary px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold text-white" data-mdb-toggle="modal" data-mdb-target="#addBlacklistModal">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Blacklist Entry</span>
                </button>
            </div>
        </div>
    </div>

<?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm py-2 px-3 mb-3"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm py-2 px-3 mb-3"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Executive Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="adm-stat-card d-flex justify-content-between align-items-center">
            <div>
                <div class="adm-stat-lbl">Total Blocked</div>
                <div class="adm-stat-val text-primary"><?php echo $total_entries; ?></div>
                <div class="adm-stat-sub"><i class="fas fa-ban me-1 text-primary"></i>All blacklisted records</div>
            </div>
            <div class="adm-icon-box adm-icon-blue">
                <i class="fas fa-shield-alt"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-card d-flex justify-content-between align-items-center">
            <div>
                <div class="adm-stat-lbl">Phone Blocks</div>
                <div class="adm-stat-val text-danger"><?php echo $stats_phone; ?></div>
                <div class="adm-stat-sub"><i class="fas fa-mobile-alt me-1 text-danger"></i>Blocked mobile numbers</div>
            </div>
            <div class="adm-icon-box adm-icon-rose">
                <i class="fas fa-phone-slash"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-card d-flex justify-content-between align-items-center">
            <div>
                <div class="adm-stat-lbl">Email Blocks</div>
                <div class="adm-stat-val text-warning"><?php echo $stats_email; ?></div>
                <div class="adm-stat-sub"><i class="fas fa-envelope me-1 text-warning"></i>Blacklisted email IDs</div>
            </div>
            <div class="adm-icon-box adm-icon-amber">
                <i class="fas fa-envelope-open-text"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-card d-flex justify-content-between align-items-center">
            <div>
                <div class="adm-stat-lbl">IP Address Blocks</div>
                <div class="adm-stat-val text-info"><?php echo $stats_ip; ?></div>
                <div class="adm-stat-sub"><i class="fas fa-network-wired me-1 text-info"></i>Network IPs restricted</div>
            </div>
            <div class="adm-icon-box adm-icon-cyan">
                <i class="fas fa-globe"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="adm-card mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by value or reason..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="filter_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="phone" <?php echo $filter_type === 'phone' ? 'selected' : ''; ?>>Phone</option>
                    <option value="email" <?php echo $filter_type === 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="ip" <?php echo $filter_type === 'ip' ? 'selected' : ''; ?>>IP Address</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-custom w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
            <?php if(!empty($search) || !empty($filter_type)): ?>
            <div class="col-md-2">
                <a href="manage_cod_blacklist.php" class="btn btn-outline-secondary btn-custom w-100"><i class="fas fa-times me-1"></i>Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Entries Table -->
<div class="adm-table-container mb-4">
    <div class="table-responsive">
        <table class="adm-table table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" style="width:50px;">#</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Reason</th>
                        <th>Date Added</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($entries && $entries->num_rows > 0): ?>
                        <?php while($e = $entries->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?php echo $e['id']; ?></td>
                            <td>
                                <?php
                                $type_badges = [
                                    'phone' => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="fas fa-phone me-1"></i>Phone</span>',
                                    'email' => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning"><i class="fas fa-envelope me-1"></i>Email</span>',
                                    'ip'    => '<span class="badge bg-info bg-opacity-10 text-info border border-info"><i class="fas fa-globe me-1"></i>IP</span>',
                                ];
                                echo $type_badges[$e['type']] ?? $e['type'];
                                ?>
                            </td>
                            <td class="fw-bold font-monospace"><?php echo htmlspecialchars($e['value']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($e['reason'] ?: '—'); ?></td>
                            <td class="text-muted small"><?php echo date('M d, Y H:i', strtotime($e['created_at'])); ?></td>
                            <td class="pe-4 text-end">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this entry from the blacklist?');">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="delete_blacklist">
                                    <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-custom"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-shield-alt fa-3x mb-3 d-block opacity-25"></i>
                                No blacklist entries found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="p-3 border-top">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
</div> <!-- /.adm-wrapper -->

<!-- Add Blacklist Modal -->
<div class="modal fade" id="addBlacklistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-ban me-2 text-danger"></i>Add to COD Blacklist</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_blacklist">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small mb-4">
                        <i class="fas fa-info-circle me-1"></i>
                        Blacklisted users will not see the COD payment option at checkout.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Block Type <span class="text-danger">*</span></label>
                        <select name="bl_type" class="form-select" required id="bl_type_select" onchange="updatePlaceholder()">
                            <option value="phone">Phone Number</option>
                            <option value="email">Email Address</option>
                            <option value="ip">IP Address</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Value <span class="text-danger">*</span></label>
                        <input type="text" name="bl_value" id="bl_value_input" class="form-control" placeholder="Enter phone number..." required>
                        <small class="text-muted" id="bl_value_hint">Phone will be normalised to last 10 digits.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason <span class="text-muted small">(Optional)</span></label>
                        <textarea name="bl_reason" class="form-control" rows="2" placeholder="e.g. Fake order attempt, multiple returns..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-custom px-4"><i class="fas fa-ban me-2"></i>Block User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePlaceholder() {
    var type = document.getElementById('bl_type_select').value;
    var input = document.getElementById('bl_value_input');
    var hint = document.getElementById('bl_value_hint');
    
    var config = {
        'phone': { placeholder: 'Enter phone number...', hint: 'Phone will be normalised to last 10 digits.' },
        'email': { placeholder: 'Enter email address...', hint: 'Email will be converted to lowercase.' },
        'ip':    { placeholder: 'Enter IP address...', hint: 'e.g. 192.168.1.100' }
    };
    
    input.placeholder = config[type].placeholder;
    hint.textContent = config[type].hint;
}
</script>

<?php include 'admin_footer.php'; ?>
