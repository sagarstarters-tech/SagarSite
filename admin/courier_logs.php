<?php
include 'admin_header.php';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Tab selection: 'queue' or 'logs'
$tab = $_GET['tab'] ?? 'queue';

if ($tab === 'queue') {
    $total_items = (int)($conn->query("SELECT COUNT(*) as c FROM courier_queue")->fetch_assoc()['c'] ?? 0);
    $items = $conn->query("
        SELECT q.*, o.total_amount, u.name as customer_name
        FROM courier_queue q
        LEFT JOIN orders o ON q.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY q.id DESC
        LIMIT $limit OFFSET $offset
    ");
} else {
    $total_items = (int)($conn->query("SELECT COUNT(*) as c FROM courier_api_logs")->fetch_assoc()['c'] ?? 0);
    $items = $conn->query("
        SELECT * FROM courier_api_logs
        ORDER BY id DESC
        LIMIT $limit OFFSET $offset
    ");
}

$total_pages = ceil($total_items / $limit);
?>

<div class="container-fluid px-4 pt-3 pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="fas fa-history text-primary me-2"></i>Courier Sync Queue &amp; API Logs</h4>
            <p class="text-muted small mb-0">Monitor background order dispatches, retries, and API communication records.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_couriers.php" class="btn btn-primary btn-custom shadow-sm">
                <i class="fas fa-sliders-h me-1"></i> Courier Settings
            </a>
            <a href="manage_orders.php" class="btn btn-light btn-custom border shadow-sm">
                <i class="fas fa-list me-1"></i> Orders Hub
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 border-bottom pb-3">
        <li class="nav-item me-2">
            <a class="nav-link rounded-pill px-4 <?php echo $tab === 'queue' ? 'active' : 'bg-white text-dark shadow-sm'; ?>" href="courier_logs.php?tab=queue">
                <i class="fas fa-tasks me-2"></i>Sync Queue
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-4 <?php echo $tab === 'logs' ? 'active' : 'bg-white text-dark shadow-sm'; ?>" href="courier_logs.php?tab=logs">
                <i class="fas fa-terminal me-2"></i>API Audit Logs
            </a>
        </li>
    </ul>

    <?php if ($tab === 'queue'): ?>
        <!-- Queue Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Queue ID</th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Next Attempt</th>
                                <th>Last Error</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items && $items->num_rows > 0): ?>
                                <?php while ($q = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">#<?php echo $q['id']; ?></td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $q['order_id']; ?>" class="fw-bold text-primary text-decoration-none">
                                                Order #<?php echo $q['order_id']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($q['customer_name'] ?? 'Guest / User'); ?></td>
                                        <td><code><?php echo htmlspecialchars($q['action']); ?></code></td>
                                        <td>
                                            <?php
                                            $badgeClass = match($q['status']) {
                                                'completed' => 'bg-success',
                                                'pending' => 'bg-warning text-dark',
                                                'processing' => 'bg-info',
                                                'failed' => 'bg-danger',
                                                'failed_permanent' => 'bg-dark',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> px-2 py-1 rounded-pill">
                                                <?php echo strtoupper($q['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $q['attempts']; ?> / <?php echo $q['max_attempts']; ?></td>
                                        <td class="small text-muted"><?php echo date('M d, H:i', strtotime($q['next_attempt_at'])); ?></td>
                                        <td class="small text-danger text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($q['last_error_message'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($q['last_error_message'] ?? '—'); ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <?php if (in_array($q['status'], ['failed', 'failed_permanent'])): ?>
                                                <button class="btn btn-sm btn-outline-primary" onclick="retryQueueItem(<?php echo $q['id']; ?>, this)">
                                                    <i class="fas fa-redo-alt me-1"></i>Retry
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center p-5 text-muted">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3 opacity-50"></i>
                                        <p class="mb-0">Queue is clear. All orders have been synced!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- API Audit Logs Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Log ID</th>
                                <th>Order</th>
                                <th>Provider</th>
                                <th>Method / Endpoint</th>
                                <th>HTTP Code</th>
                                <th>Duration</th>
                                <th>Timestamp</th>
                                <th class="pe-4 text-end">Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items && $items->num_rows > 0): ?>
                                <?php while ($log = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">#<?php echo $log['id']; ?></td>
                                        <td>
                                            <?php if ($log['order_id']): ?>
                                                <a href="order_details.php?id=<?php echo $log['order_id']; ?>" class="fw-bold text-primary text-decoration-none">
                                                    #<?php echo $log['order_id']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($log['provider_code'] ?? 'bharatship'); ?></span></td>
                                        <td>
                                            <span class="badge bg-secondary me-1"><?php echo $log['http_method']; ?></span>
                                            <span class="small font-monospace text-truncate" style="max-width: 280px; display:inline-block; vertical-align:middle;" title="<?php echo htmlspecialchars($log['endpoint_url']); ?>">
                                                <?php echo htmlspecialchars($log['endpoint_url']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['http_status_code'] >= 200 && $log['http_status_code'] < 300): ?>
                                                <span class="badge bg-success"><?php echo $log['http_status_code']; ?> OK</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?php echo $log['http_status_code']; ?> Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo $log['duration_ms']; ?>ms</td>
                                        <td class="small text-muted"><?php echo date('M d, H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td class="pe-4 text-end">
                                            <button class="btn btn-sm btn-light border" onclick="viewLogPayload(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center p-5 text-muted">
                                        <i class="fas fa-info-circle fa-3x text-muted mb-3 opacity-50"></i>
                                        <p class="mb-0">No API audit records logged yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination pagination-circle">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="courier_logs.php?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>

</div>

<!-- Payload Viewer Modal -->
<div class="modal fade" id="payloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="payloadModalTitle">API Payload Details</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold text-muted small">REQUEST PAYLOAD</h6>
                <pre class="bg-light p-3 rounded-3 border small" id="modalReqPayload" style="max-height: 200px; overflow:auto;"></pre>

                <h6 class="fw-bold text-muted small mt-3">RESPONSE PAYLOAD</h6>
                <pre class="bg-light p-3 rounded-3 border small" id="modalResPayload" style="max-height: 300px; overflow:auto;"></pre>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light btn-custom px-4" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function retryQueueItem(queueId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';
    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=retry_queue&queue_id=' + queueId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo-alt me-1"></i>Retry';
        }
    })
    .catch(err => {
        alert('Network Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-redo-alt me-1"></i>Retry';
    });
}

function viewLogPayload(log) {
    const modalEl = document.getElementById('payloadModal');
    const modal = new mdb.Modal(modalEl);

    document.getElementById('payloadModalTitle').textContent = `API Log #${log.id} (${log.http_method} ${log.http_status_code})`;

    let reqObj = log.request_payload;
    try { reqObj = JSON.stringify(JSON.parse(log.request_payload), null, 2); } catch(e){}
    document.getElementById('modalReqPayload').textContent = reqObj || 'No request body sent.';

    let resObj = log.response_payload;
    try { resObj = JSON.stringify(JSON.parse(log.response_payload), null, 2); } catch(e){}
    document.getElementById('modalResPayload').textContent = resObj || 'No response body.';

    modal.show();
}
</script>

<?php include 'admin_footer.php'; ?>
