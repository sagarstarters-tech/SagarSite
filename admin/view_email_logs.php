<?php
include 'admin_header.php';

// Pagination setup
$items_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Fetch totals
$total_q = $conn->query("SELECT COUNT(*) as cnt FROM email_logs");
$total_items = $total_q ? $total_q->fetch_assoc()['cnt'] : 0;
$total_pages = ceil($total_items / $items_per_page);

// Fetch logs
$query = "SELECT el.*, o.id as order_number, u.name as customer_name 
          FROM email_logs el 
          LEFT JOIN orders o ON el.order_id = o.id 
          LEFT JOIN users u ON o.user_id = u.id 
          ORDER BY el.created_at DESC 
          LIMIT $offset, $items_per_page";
$logs = $conn->query($query);

// Handle Delete Action
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['log_id'])) {
        $log_id = intval($_POST['log_id']);
        if ($conn->query("DELETE FROM email_logs WHERE id = $log_id")) {
            $success_msg = "Activity log entry deleted successfully.";
            // Refresh logs
            $logs = $conn->query($query);
        } else {
            $error_msg = "Error deleting log entry.";
        }
    } elseif ($action === 'clear_all') {
        if ($conn->query("TRUNCATE TABLE email_logs")) {
            $success_msg = "All email activity logs have been permanently deleted.";
            $logs = $conn->query($query); // Re-fetch (will be empty)
        } else {
            $error_msg = "Failed to clear email logs: " . $conn->error;
        }
    }
}
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-history"></i> SMTP Telemetry
            </div>
            <h1 class="adm-hero-title">Email Activity & Audit Logs</h1>
            <p class="adm-hero-subtitle">Real-time audit log of outbound transactional emails, order receipts, verification links, and dispatch notifications.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="manage_email_templates.php" class="adm-btn-white me-2">
                <i class="fas fa-envelope-open-text me-2 text-primary"></i>Email Templates
            </a>
            <?php if ($total_items > 0): ?>
                <form method="POST" class="d-inline m-0" onsubmit="return confirm('WARNING: This will permanently delete ALL email activity logs from the database. This action cannot be undone. Are you absolutely sure?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="adm-btn-white text-danger border-danger"><i class="fas fa-trash-alt me-2"></i>Clear All Data</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <div class="adm-table-container mb-4">
        <div class="table-responsive">
            <table class="adm-table table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="py-3">Date/Time</th>
                        <th class="py-3">Order ID</th>
                        <th class="py-3">Recipient</th>
                        <th class="py-3">Type</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3">Error Details</th>
                        <th class="py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($logs && $logs->num_rows > 0): ?>
                        <?php while($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="d-block mb-1 fw-semibold"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if(!empty($log['order_id'])): ?>
                                    <span class="fw-bold text-primary">#<?php echo $log['order_number']; ?></span>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['customer_name'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                            <td>
                                <?php if($log['email_type'] === 'customer_order'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1">Customer Auth</span>
                                <?php elseif($log['email_type'] === 'google_profile_reminder'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1">Profile Reminder</span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1">Admin Alert</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($log['status'] === 'success'): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check me-1"></i>Sent</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fas fa-times me-1"></i>Failed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($log['error_message'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-view-error" 
                                        data-id="<?php echo $log['id']; ?>" 
                                        data-error="<?php echo htmlspecialchars($log['error_message']); ?>">
                                        View Error
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <button type="button" class="btn btn-info btn-sm btn-custom px-3 btn-view-log"
                                        data-id="<?php echo $log['id']; ?>"
                                        data-timestamp="<?php echo date('M d, Y - h:i A', strtotime($log['created_at'])); ?>"
                                        data-email="<?php echo htmlspecialchars($log['recipient_email']); ?>"
                                        data-type="<?php echo htmlspecialchars($log['email_type']); ?>"
                                        data-status="<?php echo htmlspecialchars($log['status']); ?>"
                                        data-error="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>"
                                        data-order="<?php echo htmlspecialchars($log['order_number'] ?? ''); ?>"
                                        data-customer="<?php echo htmlspecialchars($log['customer_name'] ?? ''); ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <form method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this log entry?');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-envelope-open fa-3x mb-3 text-light"></i><br>No email activity logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

<!-- Single Clean Dynamic Log Details Modal (Placed outside table to prevent flickering) -->
<div class="modal fade" id="dynamicLogModal" tabindex="-1" aria-labelledby="dynamicLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered text-start">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold" id="dynamicLogModalLabel"><i class="fas fa-info-circle text-primary me-2"></i>Log Details <span id="modalLogId" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-1">Timestamp</label>
                    <p class="mb-0 fs-5 text-dark fw-semibold" id="modalLogTimestamp"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-1">Recipient Email</label>
                    <p class="mb-0 fs-5 text-dark" id="modalLogEmail"></p>
                </div>
                
                <div class="mb-3 d-flex justify-content-between align-items-center bg-light p-3 rounded-3">
                    <div>
                        <label class="form-label fw-bold text-muted small text-uppercase mb-1">Email Type</label>
                        <div id="modalLogType"></div>
                    </div>
                    <div class="text-end">
                        <label class="form-label fw-bold text-muted small text-uppercase mb-1">Status</label>
                        <div id="modalLogStatus"></div>
                    </div>
                </div>
                
                <div id="modalLogErrorWrapper" class="mb-3 d-none">
                    <label class="form-label fw-bold text-danger small text-uppercase">Error Message</label>
                    <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded text-danger" id="modalLogError"></div>
                </div>
                
                <div id="modalLogOrderWrapper" class="mt-4 pt-3 border-top d-none">
                    <h6 class="fw-bold mb-2 text-dark">Associated Order Data</h6>
                    <p class="mb-1"><strong>Order ID:</strong> <span id="modalLogOrderNum" class="text-primary fw-bold"></span></p>
                    <p class="mb-0"><strong>Customer Name:</strong> <span id="modalLogCustomerName"></span></p>
                    <a href="manage_orders.php" class="btn btn-outline-primary btn-sm mt-2 rounded-pill">View Orders Dashboard</a>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-2">
                <button type="button" class="btn btn-secondary btn-custom px-4 rounded-pill" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Clean Dynamic Error Details Modal (Placed outside table to prevent flickering) -->
<div class="modal fade" id="dynamicErrorModal" tabindex="-1" aria-labelledby="dynamicErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered text-start">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom border-danger bg-danger bg-opacity-10 py-3">
                <h5 class="modal-title fw-bold text-danger" id="dynamicErrorModalLabel"><i class="fas fa-exclamation-circle me-2"></i>Delivery Error <span id="modalErrorLogId"></span></h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-0 text-dark" id="modalErrorContent" style="white-space: pre-wrap; word-break: break-word;"></p>
            </div>
            <div class="modal-footer border-0 bg-light py-2">
                <button type="button" class="btn btn-secondary btn-custom px-4 rounded-pill" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let logModalInstance = null;
    let errorModalInstance = null;

    function getModal(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            return mdb.Modal.getInstance(el) || new mdb.Modal(el);
        }
        return null;
    }

    document.querySelectorAll('.btn-view-log').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const ts = this.dataset.timestamp;
            const email = this.dataset.email;
            const type = this.dataset.type;
            const status = this.dataset.status;
            const error = this.dataset.error;
            const order = this.dataset.order;
            const customer = this.dataset.customer;

            document.getElementById('modalLogId').textContent = '#' + id;
            document.getElementById('modalLogTimestamp').textContent = ts;
            document.getElementById('modalLogEmail').textContent = email;

            let typeBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1">' + (type || 'Admin Alert') + '</span>';
            if (type === 'customer_order') {
                typeBadge = '<span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1">Customer Auth</span>';
            } else if (type === 'google_profile_reminder') {
                typeBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1">Profile Reminder</span>';
            }
            document.getElementById('modalLogType').innerHTML = typeBadge;

            if (status === 'success') {
                document.getElementById('modalLogStatus').innerHTML = '<span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check me-1"></i>Sent</span>';
            } else {
                document.getElementById('modalLogStatus').innerHTML = '<span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-times me-1"></i>Failed</span>';
            }

            const errWrapper = document.getElementById('modalLogErrorWrapper');
            if (error && error.trim() !== '') {
                document.getElementById('modalLogError').textContent = error;
                errWrapper.classList.remove('d-none');
            } else {
                errWrapper.classList.add('d-none');
            }

            const orderWrapper = document.getElementById('modalLogOrderWrapper');
            if (order && order.trim() !== '') {
                document.getElementById('modalLogOrderNum').textContent = '#' + order;
                document.getElementById('modalLogCustomerName').textContent = customer || '';
                orderWrapper.classList.remove('d-none');
            } else {
                orderWrapper.classList.add('d-none');
            }

            if (!logModalInstance) {
                logModalInstance = getModal('dynamicLogModal');
            }
            if (logModalInstance) {
                logModalInstance.show();
            }
        });
    });

    document.querySelectorAll('.btn-view-error').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const error = this.dataset.error;

            document.getElementById('modalErrorLogId').textContent = '#' + id;
            document.getElementById('modalErrorContent').textContent = error || 'No error details recorded.';

            if (!errorModalInstance) {
                errorModalInstance = getModal('dynamicErrorModal');
            }
            if (errorModalInstance) {
                errorModalInstance.show();
            }
        });
    });
});
</script>
</div>
<?php include 'admin_footer.php'; ?>
