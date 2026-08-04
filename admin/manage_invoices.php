<?php
include 'admin_header.php';
require_once '../includes/InvoiceService.php';

$invoiceService = new InvoiceService($conn);

// Handle settings update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    csrf_verify();
    $invoiceService->updateSettings($_POST);
    $settingsSaved = true;
}

$invoiceSettings = $invoiceService->getInvoiceSettings();
$stats = $invoiceService->getStats();

// Pagination & filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$limit = 15;
$listing = $invoiceService->getAllInvoices($page, $limit, $statusFilter);

// WhatsApp settings check
$wa_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
$wa_settings = $wa_q ? $wa_q->fetch_assoc() : null;
$wa_enabled = ($wa_settings && $wa_settings['is_enabled'] == 1);
?>

<style>
.mi-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
}
.mi-btn-white {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18) !important;
    transition: all 0.2s ease !important;
}
.mi-btn-white:hover {
    background-color: #f1f5f9 !important;
    color: #2563eb !important;
}
.mi-btn-white *, button.mi-btn-white * {
    background: transparent !important;
    color: #0f172a !important;
    box-shadow: none !important;
}
.mi-btn-white::before, .mi-btn-white::after {
    display: none !important;
    content: none !important;
}
</style>

<div class="container-fluid py-3">

    <!-- Hero Header Banner -->
    <div class="mi-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-file-invoice me-1"></i> Automated Billing
                    </span>
                    <span class="text-white-50 small"><?php echo (int)$stats['total']; ?> total invoices generated</span>
                </div>
                <h3 class="fw-bold mb-0 text-white">Billing & Invoice Engine</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-primary px-3 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center gap-2" onclick="bulkGenerate()" id="bulkGenBtn">
                    <i class="fas fa-magic"></i>
                    <span>Generate All Pending</span>
                </button>
                <button class="btn mi-btn-white px-3 py-2 rounded-3 d-flex align-items-center gap-2" data-mdb-toggle="modal" data-mdb-target="#invoiceSettingsModal">
                    <i class="fas fa-cog text-primary"></i>
                    <span>Invoice Settings</span>
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($settingsSaved)): ?>
        <div class="alert alert-success rounded-3 py-2 px-3 mb-3 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>Invoice settings saved successfully.
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
    <?php endif; ?>

<div id="alertArea"></div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                    <i class="fas fa-file-invoice text-primary fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">Total</div>
                    <div class="fs-4 fw-bold"><?php echo (int)$stats['total']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                    <i class="fab fa-whatsapp text-success fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">Sent</div>
                    <div class="fs-4 fw-bold text-success"><?php echo (int)$stats['sent']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                    <i class="fas fa-clock text-warning fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">Not Sent</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo (int)$stats['not_sent']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                    <i class="fas fa-indian-rupee-sign text-info fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">Revenue</div>
                    <div class="fs-5 fw-bold"><?php echo $global_currency . number_format($stats['revenue'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Filter Tabs -->
<ul class="nav nav-pills mb-3 gap-1">
    <?php
    $filters = ['all' => 'All', 'generated' => 'Generated', 'sent' => 'Sent', 'viewed' => 'Viewed'];
    foreach ($filters as $key => $label):
    ?>
    <li class="nav-item">
        <a class="nav-link rounded-pill px-3 py-2 <?php echo $statusFilter === $key ? 'active' : ''; ?>"
           href="?status=<?php echo $key; ?>"><?php echo $label; ?></a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Invoices Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Invoice #</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listing['invoices'])): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listing['invoices'] as $inv): ?>
                        <tr>
                            <td class="ps-4 fw-bold">
                                <span class="text-primary"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                            </td>
                            <td>
                                <a href="order_details.php?id=<?php echo $inv['order_id']; ?>" class="text-decoration-none fw-bold">
                                    #<?php echo $inv['order_id']; ?>
                                </a>
                                <br><small class="text-muted"><?php echo ucfirst($inv['order_status']); ?></small>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($inv['customer_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($inv['customer_phone'] ?? ''); ?></small>
                            </td>
                            <td class="fw-bold"><?php echo $global_currency . number_format($inv['total_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['invoice_date'])); ?></td>
                            <td>
                                <?php if ($inv['whatsapp_sent']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                        <i class="fab fa-whatsapp me-1"></i>Sent
                                    </span>
                                <?php elseif ($inv['status'] === 'viewed'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                        <i class="fas fa-eye me-1"></i>Viewed
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">
                                        <i class="fas fa-clock me-1"></i>Generated
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="invoice_view.php?order_id=<?php echo $inv['order_id']; ?>" target="_blank"
                                       class="btn btn-primary btn-sm btn-custom px-2" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($wa_enabled): ?>
                                    <button class="btn btn-success btn-sm btn-custom px-2 text-white" title="Send via WhatsApp"
                                            onclick="sendWhatsApp(<?php echo $inv['order_id']; ?>, this)">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($inv['whatsapp_sent']): ?>
                                    <button class="btn btn-outline-success btn-sm btn-custom px-2" title="Resend"
                                            onclick="resendWhatsApp(<?php echo $inv['order_id']; ?>, this)">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-sm btn-custom px-2" title="Delete Invoice"
                                            onclick="deleteInvoice(<?php echo $inv['id']; ?>, this)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($listing['total_pages'] > 1): ?>
        <div class="p-3 border-top">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $listing['total_pages']; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($statusFilter); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Invoice Settings Modal -->
<div class="modal fade" id="invoiceSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-cog text-primary me-2"></i>Invoice Settings</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="save_settings">
                <div class="modal-body">
                    <!-- Toggles -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="invoice_auto_generate" value="1"
                                    id="autoGenToggle" <?php echo ($invoiceSettings['invoice_auto_generate'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="autoGenToggle">Auto-Generate Invoices</label>
                                <div class="text-muted small">Auto-create when order status changes to Processing/Shipped/Delivered/Completed</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="invoice_auto_send_whatsapp" value="1"
                                    id="autoWaToggle" <?php echo ($invoiceSettings['invoice_auto_send_whatsapp'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="autoWaToggle">Auto-Send via WhatsApp</label>
                                <div class="text-muted small">Automatically send invoice link when generated</div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <!-- Store Details on Invoice -->
                    <h6 class="fw-bold mb-3"><i class="fas fa-store me-2 text-muted"></i>Invoice Store Details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_prefix'] ?? 'INV'); ?>" placeholder="INV">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Store Name</label>
                            <input type="text" name="invoice_store_name" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_store_name'] ?? ''); ?>" placeholder="Your Store Name">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Store Address</label>
                            <input type="text" name="invoice_store_address" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_store_address'] ?? ''); ?>" placeholder="Full address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Phone</label>
                            <input type="text" name="invoice_store_phone" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_store_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Email</label>
                            <input type="email" name="invoice_store_email" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_store_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">GSTIN (Optional)</label>
                            <input type="text" name="invoice_gst_number" class="form-control"
                                value="<?php echo htmlspecialchars($invoiceSettings['invoice_gst_number'] ?? ''); ?>" placeholder="22AAAAA0000A1Z5">
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-3"><i class="fas fa-edit me-2 text-muted"></i>Invoice Footer</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Thank You Message</label>
                        <input type="text" name="invoice_footer_text" class="form-control"
                            value="<?php echo htmlspecialchars($invoiceSettings['invoice_footer_text'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Terms & Conditions</label>
                        <textarea name="invoice_terms" class="form-control" rows="2"><?php echo htmlspecialchars($invoiceSettings['invoice_terms'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom px-4"><i class="fas fa-save me-2"></i>Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function bulkGenerate() {
    const btn = document.getElementById('bulkGenBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';

    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=bulk_generate'
    })
    .then(r => r.json())
    .then(data => {
        showAlert(data.success ? 'success' : 'danger', data.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate All Pending';
        if (data.generated > 0) setTimeout(() => location.reload(), 1500);
    })
    .catch(() => {
        showAlert('danger', 'Network error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate All Pending';
    });
}

function sendWhatsApp(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=send_whatsapp&order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.sending_mode === 'web') {
                window.open('https://wa.me/' + data.phone + '?text=' + encodeURIComponent(data.message), '_blank');
            }
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.classList.replace('btn-success', 'btn-outline-success');
            showAlert('success', 'Invoice sent successfully!');
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
            btn.innerHTML = '<i class="fab fa-whatsapp"></i>';
            btn.disabled = false;
        }
    })
    .catch(() => { btn.innerHTML = '<i class="fab fa-whatsapp"></i>'; btn.disabled = false; });
}

function resendWhatsApp(orderId, btn) {
    if (!confirm('Resend invoice via WhatsApp?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=resend_whatsapp&order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.sending_mode === 'web') {
                window.open('https://wa.me/' + data.phone + '?text=' + encodeURIComponent(data.message), '_blank');
            }
            showAlert('success', 'Invoice resent!');
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
        }
        btn.innerHTML = '<i class="fas fa-redo"></i>';
        btn.disabled = false;
    })
    .catch(() => { btn.innerHTML = '<i class="fas fa-redo"></i>'; btn.disabled = false; });
}

function deleteInvoice(invoiceId, btn) {
    if (!confirm('Are you sure you want to delete this invoice? This cannot be undone.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&invoice_id=' + invoiceId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('tr').remove();
            showAlert('success', 'Invoice deleted successfully.');
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
            btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
            btn.disabled = false;
        }
    })
    .catch(() => { btn.innerHTML = '<i class="fas fa-trash-alt"></i>'; btn.disabled = false; });
}

function showAlert(type, msg) {
    document.getElementById('alertArea').innerHTML =
        `<div class="alert alert-${type} py-2 alert-dismissible fade show"><i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}<button type="button" class="btn-close" data-mdb-dismiss="alert"></button></div>`;
}
</script>
</div>

<?php include 'admin_footer.php'; ?>
