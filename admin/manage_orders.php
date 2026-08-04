<?php
include 'admin_header.php';
require_once '../includes/mail_functions.php';
require_once '../includes/whatsapp_functions.php';
require_once '../includes/InvoiceService.php';

// Include Tracking Module logic
require_once '../tracking_module_src/src/Config/TrackingConfig.php';
require_once '../tracking_module_src/src/Repositories/TrackingRepository.php';
$trackingConfig = new \TrackingModule\Config\TrackingConfig();
$trackingRepo = new \TrackingModule\Repositories\TrackingRepository($trackingConfig->getConnection());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $conn->query("UPDATE orders SET status='$status' WHERE id=$id");
        $trackingRepo->logStatusChange($id, $status, "Status updated to " . ucwords(str_replace('_', ' ', $status)) . " via Order Management.", 'admin');
        $success = "Order #$id status updated to $status.";
        
        // Fetch user data to send status email
        $q = $conn->query("SELECT u.email, u.name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $id");
        if ($q && $q->num_rows > 0) {
            $user = $q->fetch_assoc();
            sendOrderStatusEmail($conn, $id, $user['email'], $user['name'], $status);
            // Trigger automated WhatsApp notification
            sendAutomatedWhatsApp($conn, $id);
            // Auto-generate invoice on qualifying status change
            $invoiceService = new InvoiceService($conn);
            $invoiceService->autoGenerateOnStatusChange($id, $status);
        }
        
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM orders WHERE id=$id");
        $success = "Order deleted successfully.";
    } elseif ($action === 'clear_all') {
        // Use DELETE to trigger ON DELETE CASCADE for order_items and other related tables
        if ($conn->query("DELETE FROM orders")) {
            $success = "All orders have been permanently deleted.";
            // Optionally reset AUTO_INCREMENT
            $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
        } else {
            $success = "Failed to clear orders: " . $conn->error;
        }
    }
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';

$where_clause = "";
if ($status_filter !== 'all' && $status_filter !== '') {
    $where_clause = " WHERE o.status = '$status_filter'";
}

$orders = $conn->query("SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM orders o JOIN users u ON o.user_id = u.id $where_clause ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");

$count_sql = "SELECT COUNT(*) as c FROM orders o" . $where_clause;
$total_orders = $conn->query($count_sql)->fetch_assoc()['c'];
$total_pages = ceil($total_orders / $limit);

// Fetch WhatsApp settings 
$wa_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
$wa_settings = $wa_q ? $wa_q->fetch_assoc() : null;
$wa_enabled = ($wa_settings && $wa_settings['is_enabled'] == 1);
?>

<style>
.mo-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
}
.mo-filter-tabs {
    background: #f1f5f9;
    padding: 5px;
    border-radius: 12px;
    display: inline-flex;
    gap: 4px;
    flex-wrap: wrap;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}
.mo-filter-tab {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
}
.mo-filter-tab:hover { color: #0f172a; }
.mo-filter-tab.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}
.mo-table-container {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.mo-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mo-action-btn-group {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.mo-btn-danger-white {
    background-color: #ffffff !important;
    color: #dc2626 !important;
    border: 1px solid #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important;
    transition: all 0.2s ease !important;
}
.mo-btn-danger-white:hover {
    background-color: #fef2f2 !important;
    color: #991b1b !important;
}
</style>

<div class="container-fluid py-3">

    <!-- Hero Banner -->
    <div class="mo-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-shopping-bag me-1"></i> Store Orders
                    </span>
                    <span class="text-white-50 small"><?php echo $total_orders; ?> total orders found</span>
                </div>
                <h3 class="fw-bold mb-0 text-white">Orders Management Hub</h3>
            </div>
            <div>
                <?php if ($total_orders > 0): ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('WARNING: This will permanently delete ALL orders and their associated items from the database. This action cannot be undone. Are you absolutely sure?');">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn mo-btn-danger-white px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                            <span>Clear All Orders</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success rounded-3 py-2 px-3 mb-3 shadow-sm"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="mo-filter-tabs">
        <a href="?status=all" class="mo-filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>"><i class="fas fa-list-ul me-1"></i> All Orders</a>
        <a href="?status=pending" class="mo-filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>"><i class="fas fa-clock text-warning me-1"></i> Pending</a>
        <a href="?status=processing" class="mo-filter-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>"><i class="fas fa-cog text-primary me-1"></i> Processing</a>
        <a href="?status=shipped" class="mo-filter-tab <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>"><i class="fas fa-truck text-info me-1"></i> Shipped</a>
        <a href="?status=delivered" class="mo-filter-tab <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>"><i class="fas fa-check-circle text-success me-1"></i> Delivered</a>
        <a href="?status=completed" class="mo-filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>"><i class="fas fa-box text-success me-1"></i> Completed</a>
        <a href="?status=cancelled" class="mo-filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>"><i class="fas fa-times-circle text-secondary me-1"></i> Cancelled</a>
    </div>

    <!-- Table Container -->
    <div class="mo-table-container">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Order ID</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Order Total</th>
                        <th>Payment</th>
                        <th>Order Status</th>
                        <th class="pe-4 text-end">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($orders && $orders->num_rows > 0): ?>
                        <?php while($o = $orders->fetch_assoc()): ?>
                        <?php
                            $cName = $o['user_name'] ?? 'Guest';
                            $cInitials = strtoupper(substr($cName, 0, 2));
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-dark text-white rounded-3 px-2 py-1 fs-6">#<?php echo $o['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mo-avatar-circle"><?php echo $cInitials; ?></div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($o['user_name']); ?></div>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($o['user_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="small fw-semibold text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo $global_currency; ?><?php echo number_format($o['total_amount'], 2); ?></div>
                            </td>
                            <td>
                                <?php if($o['payment_method'] === 'cod'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-money-bill-wave me-1"></i> COD</span>
                                <?php elseif($o['payment_method'] === 'phonepe'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1"><i class="fas fa-mobile-alt me-1"></i> PhonePe</span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="fas fa-credit-card me-1"></i> Card</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-flex align-items-center m-0">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                    <select name="status" class="form-select form-select-sm rounded-3 fw-semibold border-secondary-subtle" style="min-width: 130px;" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $o['status']=='pending'?'selected':''; ?>>Pending</option>
                                        <option value="processing" <?php echo $o['status']=='processing'?'selected':''; ?>>Processing</option>
                                        <option value="partially_shipped" <?php echo $o['status']=='partially_shipped'?'selected':''; ?>>Partially Shipped</option>
                                        <option value="shipped" <?php echo $o['status']=='shipped'?'selected':''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $o['status']=='delivered'?'selected':''; ?>>Delivered</option>
                                        <option value="completed" <?php echo $o['status']=='completed'?'selected':''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $o['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="mo-action-btn-group">
                                    <?php if ($wa_enabled): ?>
                                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2 py-1" title="Send WhatsApp Update" onclick="openWhatsAppModal(<?php echo $o['id']; ?>)">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="order_details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary rounded-3 px-2 py-1" title="View Order Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="invoice_view.php?order_id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-dark rounded-3 px-2 py-1" title="View Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    <a href="manage_order_tracking.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-info text-white rounded-3 px-2 py-1" title="Update Tracking">
                                        <i class="fas fa-truck-fast"></i>
                                    </a>
                                    <form method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this order completely?');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger rounded-3 px-2 py-1" title="Delete Order"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No orders found matching criteria.</td></tr>
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($status_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($wa_enabled): ?>
<!-- WhatsApp Notification Modal -->
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-labelledby="whatsappModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="whatsappModalLabel"><i class="fab fa-whatsapp text-success me-2"></i>Send WhatsApp Update</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="waLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 text-muted">Generating message template...</p>
                </div>
                
                <form id="waForm" class="d-none">
    <?php echo csrf_input(); ?>
                    <input type="hidden" id="waOrderId">
                    <input type="hidden" id="waMode">
                    <input type="hidden" id="waToken">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Phone Number</label>
                        <input type="text" id="waCustomerPhone" class="form-control bg-light" placeholder="Include country code, e.g. 919876543210" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <textarea id="waMessage" class="form-control bg-light" rows="8" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light btn-custom text-dark" data-mdb-dismiss="modal">Cancel</button>
                <button type="button" id="waSendBtn" class="btn btn-success btn-custom px-4 disabled"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
            </div>
        </div>
    </div>
</div>

<script>
let whatsappModalInstance;

document.addEventListener('DOMContentLoaded', () => {
    whatsappModalInstance = new mdb.Modal(document.getElementById('whatsappModal'));
    
    document.getElementById('waSendBtn').addEventListener('click', function() {
        const orderId = document.getElementById('waOrderId').value;
        const phone = document.getElementById('waCustomerPhone').value;
        const message = document.getElementById('waMessage').value;
        const mode = document.getElementById('waMode').value;
        const token = document.getElementById('waToken').value; // In case they implement API here later
        
        if (!phone || !message) {
            alert("Please provide both the customer's phone number and the message.");
            return;
        }

        // 1. Send AJAX to log the attempt implicitly
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('customer_number', phone);
        formData.append('message', message);
        formData.append('sending_mode', mode);

        fetch('ajax_log_whatsapp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // 2. Execute Send Mode
                if (mode === 'web') {
                    const waLink = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                    window.open(waLink, '_blank');
                    whatsappModalInstance.hide();
                } else if (mode === 'api') {
                    alert("Message Sent Successfully via Meta API.");
                    whatsappModalInstance.hide();
                    location.reload(); // Refresh to show updated logs
                }
            } else {
                alert("Error logging the message: " + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Network error while trying to send message.");
        });
    });
});

function openWhatsAppModal(orderId) {
    // Reset modal UI
    document.getElementById('waForm').classList.add('d-none');
    document.getElementById('waLoading').classList.remove('d-none');
    document.getElementById('waSendBtn').classList.add('disabled');
    
    whatsappModalInstance.show();

    // Fetch message
    fetch(`ajax_get_whatsapp_message.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('waLoading').classList.add('d-none');
            
            if (data.error) {
                alert(data.error);
                whatsappModalInstance.hide();
                return;
            }

            // Populate form
            document.getElementById('waOrderId').value = orderId;
            document.getElementById('waCustomerPhone').value = data.customer_phone.replace(/[^0-9]/g, ''); // Strip non digits
            document.getElementById('waMessage').value = data.message;
            document.getElementById('waMode').value = data.sending_mode;
            document.getElementById('waToken').value = data.api_token;
            
            // Show Form
            document.getElementById('waForm').classList.remove('d-none');
            document.getElementById('waSendBtn').classList.remove('disabled');
            
            // Change button text contextually
            if (data.sending_mode === 'api') {
                document.getElementById('waSendBtn').innerHTML = '<i class="fas fa-server me-2"></i>Send via API';
            } else {
                document.getElementById('waSendBtn').innerHTML = '<i class="fas fa-external-link-alt me-2"></i>Open WhatsApp Web';
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error fetching order info.");
            whatsappModalInstance.hide();
        });
}
</script>
<?php endif; ?>

<?php include 'admin_footer.php'; ?>
