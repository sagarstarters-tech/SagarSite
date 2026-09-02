<?php
include 'admin_header.php';

// Include Tracking Module logic
require_once '../tracking_module_src/src/Config/TrackingConfig.php';
require_once '../tracking_module_src/src/Repositories/TrackingRepository.php';
require_once '../tracking_module_src/src/Services/TrackingService.php';

use TrackingModule\Config\TrackingConfig;
use TrackingModule\Repositories\TrackingRepository;
use TrackingModule\Services\TrackingService;

$trackingConfig = new TrackingConfig();
$pdo = $trackingConfig->getConnection();
$repo = new TrackingRepository($pdo);
$service = new TrackingService($repo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tracking') {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];
    $courier_id = intval($_POST['courier_id']);
    $tracking_num = $_POST['tracking_number'];
    $est_date = $_POST['estimated_delivery_date'];

    try {
        $res = $service->adminUpdateTracking($order_id, $courier_id, $tracking_num, $est_date, $status);
        if ($res['success']) $success = $res['message'];
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch all orders with their current tracking info
$orders_q = $conn->query("
    SELECT o.*, u.email as user_email, t.tracking_number, t.courier_id, c.name as courier_name, t.estimated_delivery_date
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN order_tracking t ON o.id = t.order_id
    LEFT JOIN courier_companies c ON t.courier_id = c.id
    ORDER BY o.created_at DESC
");

$couriers = $repo->getActiveCouriers();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-shipping-fast me-2"></i>Order Tracking Management</h4>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 py-2 mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 py-2 mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer Email</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Courier</th>
                                    <th>Tracking # (AWB)</th>
                                    <th>Est. Delivery</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($orders_q && $orders_q->num_rows > 0): ?>
                                    <?php while($o = $orders_q->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo $o['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($o['user_email']); ?></td>
                                        <td>$<?php echo number_format($o['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php 
                                                echo match($o['status']) {
                                                    'pending' => 'bg-secondary',
                                                    'processing' => 'bg-info',
                                                    'shipped', 'partially_shipped' => 'bg-primary',
                                                    'delivered', 'completed' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    default => 'bg-info'
                                                };
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $o['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($o['courier_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if (!empty($o['tracking_number'])): ?>
                                                <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($o['tracking_number']); ?></span>
                                                <button class="btn btn-link btn-sm p-0 ms-1 text-muted" title="Copy AWB" onclick="copyAWB('<?php echo htmlspecialchars($o['tracking_number']); ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $o['estimated_delivery_date'] ? date('M j, Y', strtotime($o['estimated_delivery_date'])) : 'N/A'; ?></td>
                                        <td class="text-nowrap">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-primary btn-sm px-3" style="border-radius: 50rem 0 0 50rem;"
                                                    onclick='editTracking(<?php echo htmlspecialchars(json_encode([
                                                        "id" => $o["id"],
                                                        "status" => $o["status"],
                                                        "courier_id" => $o["courier_id"],
                                                        "tracking_number" => $o["tracking_number"],
                                                        "estimated_delivery_date" => $o["estimated_delivery_date"]
                                                    ]), ENT_QUOTES, "UTF-8"); ?>)'>
                                                    <i class="fas fa-edit me-1"></i>Update
                                                </button>
                                                <?php if (!empty($o['tracking_number'])): ?>
                                                <button class="btn btn-outline-success btn-sm px-3" style="border-radius: 0 50rem 50rem 0;"
                                                    onclick="trackAWBShipment(<?php echo $o['id']; ?>, '<?php echo htmlspecialchars($o['tracking_number']); ?>', '<?php echo htmlspecialchars($o['courier_name'] ?? ''); ?>')">
                                                    <i class="fas fa-satellite-dish me-1"></i>Track AWB
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm px-3 disabled" style="border-radius: 0 50rem 50rem 0;">
                                                    <i class="fas fa-satellite-dish me-1"></i>No AWB
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center py-4">No orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Update Tracking Info</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_tracking">
                    <input type="hidden" name="order_id" id="modal_order_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Shipping Status</label>
                        <select name="status" id="modal_status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="partially_shipped">Partially Shipped</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Courier</label>
                        <select name="courier_id" id="modal_courier_id" class="form-select">
                            <option value="0">Not Assigned</option>
                            <?php foreach($couriers as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tracking Number</label>
                        <input type="text" name="tracking_number" id="modal_tracking_number" class="form-control" placeholder="AWB-XXXXXXXXX">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Estimated Delivery Date</label>
                        <input type="date" name="estimated_delivery_date" id="modal_est_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Update Tracking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- AWB Shipment Tracking Modal -->
<div class="modal fade" id="awbTrackingModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 bg-gradient" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                <div class="d-flex align-items-center">
                    <div class="bg-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-satellite-dish text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">AWB Shipment Tracking</h5>
                        <small class="text-white-50" id="awb_modal_subtitle">Loading...</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="awb_modal_body">
                <!-- Dynamic content injected by JS -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-3 text-muted">Fetching shipment details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let trackingModal;
let awbModal;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof mdb !== 'undefined') {
        trackingModal = new mdb.Modal(document.getElementById('trackingModal'));
        awbModal = new mdb.Modal(document.getElementById('awbTrackingModal'));
    } else {
        console.error('MDB Library not loaded');
    }
});

function editTracking(order) {
    console.log('Editing tracking for order:', order);
    document.getElementById('modal_order_id').value = order.id;
    document.getElementById('modal_status').value = order.status;
    document.getElementById('modal_courier_id').value = order.courier_id || 0;
    document.getElementById('modal_tracking_number').value = order.tracking_number || '';
    document.getElementById('modal_est_date').value = order.estimated_delivery_date || '';
    
    if (trackingModal) {
        trackingModal.show();
    } else {
        trackingModal = new mdb.Modal(document.getElementById('trackingModal'));
        trackingModal.show();
    }
}

/**
 * Copy AWB number to clipboard
 */
function copyAWB(awb) {
    navigator.clipboard.writeText(awb).then(() => {
        // Show a brief toast notification
        showToast('AWB number copied: ' + awb, 'success');
    }).catch(() => {
        // Fallback for older browsers
        const input = document.createElement('input');
        input.value = awb;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showToast('AWB number copied: ' + awb, 'success');
    });
}

/**
 * Track AWB Shipment — opens tracking details in modal
 */
function trackAWBShipment(orderId, awb, courierName) {
    // Update modal subtitle
    document.getElementById('awb_modal_subtitle').textContent = 
        `Order #${orderId} • ${courierName || 'Courier'} • AWB: ${awb}`;
    
    // Show loading state
    document.getElementById('awb_modal_body').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
            <p class="mt-3 text-muted">Fetching shipment details...</p>
        </div>
    `;
    
    // Show modal
    if (!awbModal) awbModal = new mdb.Modal(document.getElementById('awbTrackingModal'));
    awbModal.show();
    
    // Fetch AWB tracking data from our secure API
    fetch(`../api/awb_track.php?order_id=${orderId}&admin=1`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderAWBTrackingPanel(data.data);
            } else {
                document.getElementById('awb_modal_body').innerHTML = `
                    <div class="alert alert-warning m-4 rounded-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>${data.message || 'Unable to fetch tracking information.'}
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('AWB Tracking Error:', err);
            document.getElementById('awb_modal_body').innerHTML = `
                <div class="alert alert-danger m-4 rounded-3">
                    <i class="fas fa-times-circle me-2"></i>Network error occurred while fetching tracking data.
                </div>
            `;
        });
}

/**
 * Render the AWB tracking panel inside the modal
 */
function renderAWBTrackingPanel(data) {
    const hasUrl = data.tracking_url && data.tracking_url.trim() !== '';
    const courierName = data.courier_name || 'Courier';
    const awb = data.awb;
    const status = data.order_status || 'processing';
    const statusFormatted = data.order_status_formatted || 'Processing';
    const estDelivery = data.estimated_delivery_date || 'In Transit';
    const history = data.history || [];

    // Stage calculation for progress bar
    const stages = ['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered'];
    let currentStageIndex = 1;
    if (status === 'shipped' || status === 'partially_shipped') currentStageIndex = 2;
    if (status === 'out_for_delivery') currentStageIndex = 3;
    if (status === 'delivered' || status === 'completed') currentStageIndex = 4;
    if (status === 'cancelled') currentStageIndex = -1;

    let timelineHtml = '';
    if (history.length > 0) {
        timelineHtml = `
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-history text-primary me-2"></i>Status History Timeline</h6>
                    <div class="timeline-stepper">
                        ${history.map((h, idx) => `
                            <div class="d-flex align-items-start mb-3">
                                <div class="badge bg-primary rounded-circle p-2 me-3 mt-1" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-check" style="font-size: 10px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-dark">${h.status_formatted}</strong>
                                        <small class="text-muted font-monospace">${h.created_at}</small>
                                    </div>
                                    ${h.notes ? `<p class="text-muted small mb-0 mt-1">${h.notes}</p>` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }

    let html = `
        <div class="p-4">
            <!-- AWB Info Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-light border-0 rounded-4 h-100 shadow-sm">
                        <div class="card-body text-center p-3">
                            <div class="text-muted small mb-1"><i class="fas fa-hashtag me-1"></i>Order ID</div>
                            <div class="fw-bold fs-5 text-dark">#${data.order_id}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light border-0 rounded-4 h-100 shadow-sm">
                        <div class="card-body text-center p-3">
                            <div class="text-muted small mb-1"><i class="fas fa-truck-fast me-1"></i>Courier Partner</div>
                            <div class="fw-bold fs-5 text-primary">${courierName}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light border-0 rounded-4 h-100 shadow-sm">
                        <div class="card-body text-center p-3">
                            <div class="text-muted small mb-1"><i class="fas fa-barcode me-1"></i>AWB Number</div>
                            <div class="fw-bold fs-5 text-success font-monospace">${awb}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light border-0 rounded-4 h-100 shadow-sm">
                        <div class="card-body text-center p-3">
                            <div class="text-muted small mb-1"><i class="fas fa-calendar-check me-1"></i>Est. Delivery</div>
                            <div class="fw-bold fs-5 text-dark">${estDelivery}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prominent Direct Tracker Launch Card -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #f0f7ff 0%, #e6f0fa 100%); border: 2px dashed #0d6efd !important;">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex p-3 bg-white rounded-circle shadow-sm mb-3 text-primary">
                        <i class="fas fa-shipping-fast fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Official Carrier Live Tracking</h5>
                    <p class="text-muted small mb-3">Track real-time dispatch, transit checkpoints and out-for-delivery status directly on <strong>${courierName}</strong>.</p>
                    
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        ${hasUrl ? `
                            <a href="${data.tracking_url}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-lg rounded-pill px-4 shadow-sm fw-bold">
                                <i class="fas fa-external-link-alt me-2"></i>Open Official ${courierName} Tracking Page
                            </a>
                        ` : ''}
                        <button class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm" onclick="copyAWB('${awb}')">
                            <i class="fas fa-copy me-2"></i>Copy AWB: ${awb}
                        </button>
                    </div>

                    <!-- Alternate Trackers -->
                    <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-center gap-2">
                        <span class="small text-muted me-2">Alternative Trackers:</span>
                        <a href="${data.global_17track_url || `https://t.17track.net/en#nums=${awb}`}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                            <i class="fas fa-globe me-1"></i>17Track
                        </a>
                        <a href="${data.shiprocket_url || `https://shiprocket.co/tracking/${awb}`}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3">
                            <i class="fas fa-rocket me-1"></i>Shiprocket
                        </a>
                        <a href="https://www.google.com/search?q=${encodeURIComponent(courierName + ' tracking ' + awb)}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="fab fa-google me-1"></i>Google Search
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status History Timeline -->
            ${timelineHtml}
        </div>
    `;
    
    document.getElementById('awb_modal_body').innerHTML = html;
}

/**
 * Show a brief toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'info'} position-fixed shadow-lg border-0 rounded-3`;
    toast.style.cssText = 'bottom: 20px; right: 20px; z-index: 99999; min-width: 250px; animation: fadeInUp 0.3s ease;';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function clearTrackingLogs() {
    if (!confirm('Are you sure you want to clear ALL tracking status history logs? This cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'admin_clear_tracking_logs');

    fetch('../tracking_module_src/TrackingAPI.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + (data.message || data.error));
        }
    })
    .catch(err => {
        alert('Fatal error occurred.');
        console.error(err);
    });
}
</script>

<style>
/* AWB Tracking Modal Styles */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#awbTrackingModal .modal-content {
    overflow: hidden;
}

#awbTrackingModal .card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#awbTrackingModal .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}

.rounded-pill-start {
    border-radius: 50rem 0 0 50rem !important;
}

.rounded-pill-end {
    border-radius: 0 50rem 50rem 0 !important;
}
</style>

<?php include 'admin_footer.php'; ?>
