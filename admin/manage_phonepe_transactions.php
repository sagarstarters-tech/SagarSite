<?php
include 'admin_header.php';

// Handle Clear All Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    if ($conn->query("TRUNCATE TABLE phonepe_transactions")) {
        $success = "All transaction statements have been permanently deleted.";
    } else {
        $error = "Failed to clear transactions: " . $conn->error;
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch total count for pagination
$count_query = "SELECT COUNT(*) as total FROM phonepe_transactions";
$total_result = $conn->query($count_query);
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch transactions
$query = "
    SELECT pt.*, o.user_id, o.total_amount, o.status as order_status, u.name as customer_name, u.email as customer_email
    FROM phonepe_transactions pt
    LEFT JOIN orders o ON pt.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY pt.created_at DESC
    LIMIT $limit OFFSET $offset
";
$transactions = $conn->query($query);
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-credit-card"></i> Payment Gateway Telemetry
            </div>
            <h1 class="adm-hero-title">PhonePe Transaction Statements</h1>
            <p class="adm-hero-subtitle">Review server-to-server webhook telemetry, UPI callback receipts, and raw API transaction logs.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="manage_settings.php" class="adm-btn-white me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Settings
            </a>
            <?php if ($total_rows > 0): ?>
                <form method="POST" class="d-inline m-0" onsubmit="return confirm('WARNING: This will permanently delete ALL PhonePe transaction logs from the database. This action cannot be undone. Are you absolutely sure?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-outline-danger bg-white border-0 text-danger rounded-pill px-3 py-2 fw-bold shadow-sm">
                        <i class="fas fa-trash-alt me-2"></i>Clear All Logs
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-check-circle fs-4 me-3 text-success"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fs-4 me-3 text-danger"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <div class="adm-table-container">
        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-receipt me-2 text-primary"></i>All Transactions Log</h5>
                <p class="text-muted small mb-0"><?php echo $total_rows; ?> total recorded payment attempts</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Transaction ID</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th class="text-end">Raw Payload</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions && $transactions->num_rows > 0): ?>
                        <?php while($row = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted small"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="fw-bold font-monospace small text-dark"><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                                <td>
                                    <a class="badge bg-primary text-white text-decoration-none px-2 py-1" href="manage_orders.php">
                                        #<?php echo $row['order_id']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['customer_name'] ?? 'Guest'); ?></span>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['customer_email'] ?? '-'); ?></small>
                                    </div>
                                </td>
                                <td class="fw-bold text-dark fs-6">
                                    <?php echo htmlspecialchars($global_settings['currency_symbol'] ?? '₹'); ?><?php echo number_format($row['amount'], 2); ?>
                                </td>
                                <td>
                                    <?php 
                                        $bg = 'bg-secondary';
                                        if ($row['status'] === 'SUCCESS') $bg = 'bg-success';
                                        elseif ($row['status'] === 'FAILED') $bg = 'bg-danger';
                                        elseif ($row['status'] === 'PENDING') $bg = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $bg; ?> rounded-pill px-3 py-1"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-light btn-sm text-primary rounded-pill px-3 fw-bold border view-payload-btn" 
                                            data-payload="<?php echo htmlspecialchars($row['raw_payload']); ?>"
                                            data-refid="<?php echo htmlspecialchars($row['provider_reference_id'] ?? 'N/A'); ?>">
                                        <i class="fas fa-code me-1"></i> Data
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fs-1 d-block mb-3 opacity-25"></i>
                                No PhonePe transactions found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="p-4 border-top">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 rounded-pill px-3 mx-1" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link shadow-sm border-0 rounded-circle mx-1" style="width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 rounded-pill px-3 mx-1" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Raw Payload Modal -->
<div class="modal fade" id="payloadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">PhonePe Raw Transaction Data</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-3">
                    <label class="fw-bold text-secondary text-uppercase small">Provider Reference ID (Bank API)</label>
                    <div id="modal-ref-id" class="font-monospace fw-bold bg-white p-2 rounded border"></div>
                </div>
                <div>
                    <label class="fw-bold text-secondary text-uppercase small">Raw Webhook Payload JSON</label>
                    <pre id="modal-payload-content" class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 0.85rem; white-space: pre-wrap;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const payloadBtns = document.querySelectorAll('.view-payload-btn');
    payloadBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const rawData = this.getAttribute('data-payload');
            const refId = this.getAttribute('data-refid');
            
            let formattedJson = rawData;
            try {
                if (rawData && rawData !== '') {
                    const parsed = JSON.parse(rawData);
                    formattedJson = JSON.stringify(parsed, null, 2);
                } else {
                    formattedJson = "No raw payload data logged.";
                }
            } catch(e) { }

            document.getElementById('modal-ref-id').innerText = refId;
            document.getElementById('modal-payload-content').innerText = formattedJson;
            
            const modal = new mdb.Modal(document.getElementById('payloadModal'));
            modal.show();
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
