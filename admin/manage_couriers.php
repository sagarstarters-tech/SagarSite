<?php
include 'admin_header.php';
require_once __DIR__ . '/../courier_module/Database/CourierSchemaBootstrap.php';
require_once __DIR__ . '/../courier_module/Services/CourierCryptoService.php';

// Auto-create missing tables on production if not existing
\CourierModule\Database\CourierSchemaBootstrap::ensureTablesExist($conn);

// Fetch current courier integrations
$integrations_q = $conn->query("SELECT * FROM courier_integrations ORDER BY id ASC");
$integrations = [];
if ($integrations_q) {
    while ($row = $integrations_q->fetch_assoc()) {
        $integrations[$row['provider_code']] = $row;
    }
}

$bharatship = $integrations['bharatship'] ?? [
    'id' => 1,
    'provider_code' => 'bharatship',
    'provider_name' => 'BharatShip',
    'api_base_url' => 'https://app.bharatship.com/',
    'api_token' => '',
    'pickup_address_id' => 0,
    'default_courier_ship_type' => 2,
    'default_express' => 'surface',
    'is_enabled' => 0,
    'is_default' => 1,
    'auto_sync_orders' => 1
];

$maskedToken = \CourierModule\Services\CourierCryptoService::mask($bharatship['api_token'] ?? '');

// Fetch synced warehouses from DB
$warehouses_q = $conn->query("SELECT * FROM courier_warehouses ORDER BY id ASC");
$savedWarehouses = [];
if ($warehouses_q) {
    while ($wh = $warehouses_q->fetch_assoc()) {
        $savedWarehouses[] = $wh;
    }
}

// Counts
$total_shipments = (int)($conn->query("SELECT COUNT(*) as c FROM courier_shipments")->fetch_assoc()['c'] ?? 0);
$pending_queue   = (int)($conn->query("SELECT COUNT(*) as c FROM courier_queue WHERE status IN ('pending', 'processing')")->fetch_assoc()['c'] ?? 0);
$failed_queue    = (int)($conn->query("SELECT COUNT(*) as c FROM courier_queue WHERE status IN ('failed', 'failed_permanent')")->fetch_assoc()['c'] ?? 0);
?>

<div class="container-fluid px-4 pt-3 pb-5">
    
    <!-- Hero / Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="fas fa-truck-moving text-primary me-2"></i>Courier &amp; Shipping Aggregators</h4>
            <p class="text-muted small mb-0">Configure BharatShip API integration, automated AWB generation, and pickup warehouse hubs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="courier_logs.php" class="btn btn-outline-secondary btn-custom shadow-sm">
                <i class="fas fa-history me-1"></i> API Logs &amp; Queue
                <?php if ($failed_queue > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $failed_queue; ?> Failed</span>
                <?php endif; ?>
            </a>
            <a href="manage_orders.php" class="btn btn-light btn-custom border shadow-sm">
                <i class="fas fa-list me-1"></i> All Orders
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-boxes fa-2x"></i>
                    </div>
                    <div>
                        <small class="text-muted fw-semibold">Total Synced Shipments</small>
                        <h3 class="fw-bold mb-0"><?php echo number_format($total_shipments); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning me-3">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div>
                        <small class="text-muted fw-semibold">Pending in Sync Queue</small>
                        <h3 class="fw-bold mb-0 text-warning"><?php echo number_format($pending_queue); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-danger bg-opacity-10 text-danger me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <small class="text-muted fw-semibold">Failed Dispatches</small>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo number_format($failed_queue); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BharatShip Active Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2 bg-dark text-white fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="fas fa-shipping-fast text-warning fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">BharatShip Integration</h5>
                    <small class="text-muted">Primary Logistics Aggregator (REST API &bull; Bearer Token Auth)</small>
                </div>
            </div>
            <div>
                <?php if ($bharatship['is_enabled']): ?>
                    <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary px-3 py-2 rounded-pill">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-4">
            <form id="bharatshipForm">
                <input type="hidden" name="action" value="save_integration">
                <input type="hidden" name="id" value="<?php echo $bharatship['id'] ?? 1; ?>">
                <input type="hidden" name="provider_code" value="bharatship">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">API Base URL</label>
                        <input type="url" name="api_base_url" class="form-control" value="<?php echo htmlspecialchars($bharatship['api_base_url']); ?>" required>
                        <div class="form-text small">Default: <code>https://app.bharatship.com/</code></div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-muted mb-0">API Bearer Token</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold text-info" data-mdb-toggle="modal" data-mdb-target="#generateTokenModal">
                                <i class="fas fa-key me-1"></i>Generate from Login
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="password" name="api_token" id="bsApiToken" class="form-control" placeholder="<?php echo !empty($maskedToken) ? $maskedToken : 'Paste your BharatShip Bearer Token'; ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()"><i class="fas fa-eye" id="tokenEyeIcon"></i></button>
                        </div>
                        <div class="form-text small">Stored securely via AES-256-CBC encryption.</div>
                    </div>

                    <!-- Pickup Warehouse Selection -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-muted mb-0">Default Pickup Warehouse (<code>pickup_address_id</code>)</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold text-primary" onclick="syncWarehousesFromApi()" id="syncWhBtn">
                                    <i class="fas fa-sync-alt me-1"></i>Sync from BharatShip
                                </button>
                                <span class="text-muted">|</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold text-success" data-mdb-toggle="modal" data-mdb-target="#addWarehouseModal">
                                    <i class="fas fa-plus-circle me-1"></i>+ Add Warehouse
                                </button>
                            </div>
                        </div>
                        <select name="pickup_address_id" id="pickupAddressSelect" class="form-select">
                            <option value="0">-- Select Pickup Warehouse --</option>
                            <?php foreach ($savedWarehouses as $wh): ?>
                                <option value="<?php echo $wh['warehouse_id'] ?: $wh['warehouse_code']; ?>" <?php echo ($bharatship['pickup_address_id'] == ($wh['warehouse_id'] ?: $wh['warehouse_code'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wh['warehouse_name']); ?> (ID: <?php echo $wh['warehouse_id'] ?: $wh['warehouse_code']; ?> &bull; <?php echo htmlspecialchars($wh['city'] . ', ' . $wh['pincode']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Select the pickup warehouse registered in your BharatShip Dashboard or click <b>+ Add Warehouse</b> to register one now.</div>
                    </div>

                    <!-- Default Courier Mode -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Courier Assignment Mode (<code>courier_ship_type</code>)</label>
                        <select name="default_courier_ship_type" class="form-select">
                            <option value="2" <?php echo ($bharatship['default_courier_ship_type'] == 2) ? 'selected' : ''; ?>>Auto-Assign Best Courier (courier_ship_type = 2) [Recommended]</option>
                            <option value="1" <?php echo ($bharatship['default_courier_ship_type'] == 1) ? 'selected' : ''; ?>>Specific Partner (courier_ship_type = 1)</option>
                        </select>
                        <div class="form-text small">Auto mode lets BharatShip select the fastest/cheapest available partner.</div>
                    </div>

                    <!-- Express Mode -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Default Shipping Mode (<code>express</code>)</label>
                        <select name="default_express" class="form-select">
                            <option value="surface" <?php echo ($bharatship['default_express'] === 'surface') ? 'selected' : ''; ?>>Surface (Standard for heavy/agricultural equipment)</option>
                            <option value="air" <?php echo ($bharatship['default_express'] === 'air') ? 'selected' : ''; ?>>Air (Express parcel delivery)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Automation Settings</label>
                        <div class="d-flex flex-column gap-2 mt-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" id="bsEnabled" value="1" <?php echo $bharatship['is_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="bsEnabled">Enable BharatShip Integration</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="bsDefault" value="1" <?php echo $bharatship['is_default'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="bsDefault">Set as Primary Default Courier</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="auto_sync_orders" id="bsAutoSync" value="1" <?php echo $bharatship['auto_sync_orders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="bsAutoSync">Auto-sync orders via Background Cron</label>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-info btn-custom px-4" id="testConnBtn" onclick="testBharatShipConnection()">
                        <i class="fas fa-plug me-2"></i>Test API Connection
                    </button>
                    <button type="submit" class="btn btn-primary btn-custom px-5 shadow-sm" id="saveBsBtn">
                        <i class="fas fa-save me-2"></i>Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Future Courier Providers (Modular expansion placeholders) -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
            <h5 class="fw-bold mb-0 text-secondary"><i class="fas fa-puzzle-piece me-2"></i>Additional Courier Aggregators (Modular Ready)</h5>
            <small class="text-muted">You can activate these aggregators in future updates without changing the checkout code.</small>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 bg-light opacity-75">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Shiprocket</h6>
                            <span class="badge bg-light text-dark border">Available for Add-on</span>
                        </div>
                        <p class="small text-muted mb-0">Multi-carrier aggregator supporting 17+ logistics partners.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 bg-light opacity-75">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Delhivery Direct</h6>
                            <span class="badge bg-light text-dark border">Available for Add-on</span>
                        </div>
                        <p class="small text-muted mb-0">Direct corporate API integration with Delhivery B2C Express &amp; Surface.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 bg-light opacity-75">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">NimbusPost</h6>
                            <span class="badge bg-light text-dark border">Available for Add-on</span>
                        </div>
                        <p class="small text-muted mb-0">Multi-carrier aggregator with automated COD remittance.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Generate Token Modal -->
<div class="modal fade" id="generateTokenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-key text-info me-2"></i>Generate Token from BharatShip Login</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="generateTokenForm">
                <input type="hidden" name="action" value="generate_auth_token">
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Enter your registered BharatShip login credentials to automatically generate and save your Bearer Token.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">BharatShip Email *</label>
                        <input type="email" name="email" class="form-control" value="sagarstarters@gmail.com" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">BharatShip Password *</label>
                        <input type="password" name="password" class="form-control" value="Pramod@2026" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light btn-custom px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white btn-custom px-4 shadow-sm" id="genTokenSubmitBtn">
                        <i class="fas fa-magic me-1"></i>Generate &amp; Save Token
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Connection Modal -->
<div class="modal fade" id="testResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="testModalTitle">API Test Result</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4" id="testModalBody">
                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                <p>Testing connection to BharatShip...</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-light btn-custom px-4" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Warehouse Modal -->
<div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-warehouse text-success me-2"></i>Register New Warehouse on BharatShip</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addWarehouseForm">
                <input type="hidden" name="action" value="add_warehouse">
                <input type="hidden" name="provider_code" value="bharatship">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Warehouse Name *</label>
                        <input type="text" name="warehouse_name" class="form-control" value="Sagar Starters Store" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Contact Person *</label>
                            <input type="text" name="contact_name" class="form-control" value="Sagar Store Manager" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Phone Number (10 Digits) *</label>
                            <input type="tel" name="contact_phone" class="form-control" value="918573934013" maxlength="10" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Street Address *</label>
                        <textarea name="address_line1" class="form-control" rows="2" placeholder="Full store pickup address" required>Shop No 5, Near Market, Main Road</textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">City *</label>
                            <input type="text" name="city" class="form-control" value="Prayagraj" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">State *</label>
                            <input type="text" name="state" class="form-control" value="Uttar Pradesh" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Pincode (6 Digits) *</label>
                            <input type="text" name="pincode" class="form-control" value="211001" maxlength="6" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light btn-custom px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-custom px-4 shadow-sm" id="createWhBtn">
                        <i class="fas fa-plus me-1"></i>Create on BharatShip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTokenVisibility() {
    const input = document.getElementById('bsApiToken');
    const icon = document.getElementById('tokenEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Sync Warehouses from BharatShip API
function syncWarehousesFromApi() {
    const btn = document.getElementById('syncWhBtn');
    const tokenVal = document.getElementById('bsApiToken')?.value || '';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';

    let bodyData = 'action=fetch_warehouses&provider_code=bharatship';
    if (tokenVal) {
        bodyData += '&api_token=' + encodeURIComponent(tokenVal);
    }

    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: bodyData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Sync from BharatShip';
        if (data.success && data.warehouses && data.warehouses.length > 0) {
            const select = document.getElementById('pickupAddressSelect');
            select.innerHTML = '<option value="0">-- Select Pickup Warehouse --</option>';
            data.warehouses.forEach(wh => {
                const whId = wh.warehouse_id || wh.id || wh.pickup_address_id || 1;
                const whName = wh.warehouse_name || wh.name || 'Warehouse';
                const city = wh.city_name || wh.city || '';
                const pin = wh.pincode || '';
                select.innerHTML += `<option value="${whId}">${whName} (ID: ${whId} &bull; ${city} ${pin})</option>`;
            });
            // Auto-select first warehouse
            if (select.options.length > 1) {
                select.selectedIndex = 1;
            }
            alert('Successfully synced ' + data.warehouses.length + ' warehouses from BharatShip!');
        } else {
            alert('Notice: BharatShip returned 0 active warehouses.\n\nClick "+ Add Warehouse" to register your store pickup address on BharatShip instantly.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Sync from BharatShip';
        alert('Network Error: ' + err.message);
    });
}

// Create Warehouse Form Submit
document.getElementById('addWarehouseForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('createWhBtn');
    const tokenVal = document.getElementById('bsApiToken')?.value || '';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registering on BharatShip...';

    const formData = new FormData(this);
    if (tokenVal) {
        formData.append('api_token', tokenVal);
    }
    const params = new URLSearchParams(formData);

    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        body: params
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus me-1"></i>Create on BharatShip';
        if (data.success) {
            alert('Warehouse registered on BharatShip successfully! ID: ' + (data.warehouse_id || 'Active'));
            location.reload();
        } else {
            alert('Failed to create warehouse: ' + (data.message || 'Please check details'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus me-1"></i>Create on BharatShip';
        alert('Network Error: ' + err.message);
    });
});

// Test Connection
function testBharatShipConnection() {
    const btn = document.getElementById('testConnBtn');
    const tokenVal = document.getElementById('bsApiToken')?.value || '';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';

    const modalEl = document.getElementById('testResultModal');
    const modal = new mdb.Modal(modalEl);
    const body = document.getElementById('testModalBody');
    body.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i><p>Testing connection to BharatShip...</p>';
    modal.show();

    let bodyData = 'action=test_connection&provider_code=bharatship';
    if (tokenVal) {
        bodyData += '&api_token=' + encodeURIComponent(tokenVal);
    }

    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: bodyData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug me-2"></i>Test API Connection';
        if (data.success) {
            body.innerHTML = `
                <div class="text-success mb-3"><i class="fas fa-check-circle fa-4x"></i></div>
                <h5 class="fw-bold text-success mb-2">Connected Successfully!</h5>
                <p class="text-muted small mb-0">${data.message || 'BharatShip API responded with 200 OK.'}</p>
            `;
        } else {
            body.innerHTML = `
                <div class="text-danger mb-3"><i class="fas fa-times-circle fa-4x"></i></div>
                <h5 class="fw-bold text-danger mb-2">Connection Failed</h5>
                <p class="text-danger small mb-0">${data.message || 'Unable to connect to BharatShip API.'}</p>
            `;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug me-2"></i>Test API Connection';
        body.innerHTML = `
            <div class="text-danger mb-3"><i class="fas fa-exclamation-triangle fa-4x"></i></div>
            <h5 class="fw-bold text-danger mb-2">Network Error</h5>
            <p class="text-danger small mb-0">${err.message}</p>
        `;
    });
}

// Generate Token from Login Form Submit
document.getElementById('generateTokenForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('genTokenSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Authenticating...';

    const formData = new FormData(this);
    const params = new URLSearchParams(formData);

    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        body: params
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-1"></i>Generate &amp; Save Token';
        if (data.success) {
            alert('Success: ' + data.message);
            location.reload();
        } else {
            alert('Failed: ' + (data.message || 'Authentication error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-1"></i>Generate &amp; Save Token';
        alert('Network Error: ' + err.message);
    });
});

// Save Configuration
document.getElementById('bharatshipForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBsBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

    const formData = new FormData(this);
    const params = new URLSearchParams(formData);

    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        body: params
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Configuration';
        if (data.success) {
            alert('BharatShip configuration saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to save configuration'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Configuration';
        alert('Server Error: ' + err.message);
    });
});
</script>

<?php include 'admin_footer.php'; ?>
