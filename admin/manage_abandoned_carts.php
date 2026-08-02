<?php
include 'admin_header.php';
require_once '../includes/AbandonedCartService.php';

$abandonedCartService = new AbandonedCartService($conn);
$dashboardData = $abandonedCartService->getAdminDashboardData();
$stats = $dashboardData['stats'];
$settings = $dashboardData['settings'];
?>
<style>
.section-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid #3b5976;
}
.reminder-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 4px;
}
.reminder-dot.sent { background: #28a745; }
.reminder-dot.pending { background: #dee2e6; }
.stat-card { transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-2px); }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class='fas fa-cart-arrow-down me-2'></i>Cart Abandonment Recovery</h2>
            <p class="text-muted mb-0">Track and recover abandoned carts via automated WhatsApp reminders.</p>
        </div>
        <div>
            <button class="btn btn-primary" type="button" data-mdb-toggle="collapse" data-mdb-target="#settingsCollapse" aria-expanded="false" aria-controls="settingsCollapse">
                <i class="fas fa-cog me-2"></i> Settings
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4" id="statsRow">
        <div class="col-xl-3 col-md-6 mb-4 stat-card">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: #e3f2fd;">
                        <i class="fas fa-shopping-cart fa-lg" style="color: #1565c0;"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">Active Abandoned</div>
                        <div class="fs-4 fw-bold" style="color: #1565c0;" id="stat_active_carts"><?php echo htmlspecialchars($stats['active_carts'] ?? '0'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4 stat-card">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: #e8f5e9;">
                        <i class="fas fa-chart-line fa-lg" style="color: #2e7d32;"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">Recovery Rate</div>
                        <div class="fs-4 fw-bold" style="color: #2e7d32;" id="stat_recovery_rate"><?php echo htmlspecialchars($stats['recovery_rate'] ?? '0'); ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4 stat-card">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: #fff3e0;">
                        <i class="fas fa-rupee-sign fa-lg" style="color: #e65100;"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">Lost Revenue</div>
                        <div class="fs-4 fw-bold" style="color: #e65100;" id="stat_lost_value"><?php echo isset($global_currency) ? htmlspecialchars($global_currency) : '₹'; ?><?php echo htmlspecialchars($stats['total_lost_value'] ?? '0'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4 stat-card">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: #fce4ec;">
                        <i class="fas fa-bell fa-lg" style="color: #c62828;"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">Pending Reminders</div>
                        <div class="fs-4 fw-bold" style="color: #c62828;" id="stat_pending"><?php echo htmlspecialchars($stats['pending_reminders'] ?? '0'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Panel -->
    <div class="collapse mb-4" id="settingsCollapse">
        <div class="card border-0 shadow-sm rounded-4 section-card">
            <div class="card-body">
                <h5 class="card-title mb-4"><i class="fas fa-cogs me-2"></i> Abandoned Cart Settings</h5>
                <form id="settingsForm">
                    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                    
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?> value="1">
                        <label class="form-check-label fw-bold" for="is_enabled">Enable Cart Abandonment Recovery</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder 1 Delay (minutes)</label>
                            <input type="number" class="form-control" name="reminder_1_delay" value="<?php echo htmlspecialchars($settings['reminder_1_delay'] ?? '30'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder 2 Delay (minutes)</label>
                            <input type="number" class="form-control" name="reminder_2_delay" value="<?php echo htmlspecialchars($settings['reminder_2_delay'] ?? '360'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder 3 Delay (minutes)</label>
                            <input type="number" class="form-control" name="reminder_3_delay" value="<?php echo htmlspecialchars($settings['reminder_3_delay'] ?? '1440'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder 4 Delay (minutes)</label>
                            <input type="number" class="form-control" name="reminder_4_delay" value="<?php echo htmlspecialchars($settings['reminder_4_delay'] ?? '4320'); ?>">
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> <strong>Variables available:</strong> {CustomerName}, {ProductNames}, {CartTotal}, {RecoveryLink}, {CouponCode}, {CouponDiscount}
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Reminder 1</label>
                            <textarea class="form-control mb-2" name="reminder_1_message" rows="3"><?php echo htmlspecialchars($settings['reminder_1_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold text-muted">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_1" id="metaTpl1" value="<?php echo htmlspecialchars($settings['meta_template_1'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl1" title="Fetch Templates"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Reminder 2</label>
                            <textarea class="form-control mb-2" name="reminder_2_message" rows="3"><?php echo htmlspecialchars($settings['reminder_2_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold text-muted">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_2" id="metaTpl2" value="<?php echo htmlspecialchars($settings['meta_template_2'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl2" title="Fetch Templates"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Reminder 3</label>
                            <textarea class="form-control mb-2" name="reminder_3_message" rows="3"><?php echo htmlspecialchars($settings['reminder_3_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold text-muted">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_3" id="metaTpl3" value="<?php echo htmlspecialchars($settings['meta_template_3'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl3" title="Fetch Templates"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Reminder 4</label>
                            <textarea class="form-control mb-2" name="reminder_4_message" rows="3"><?php echo htmlspecialchars($settings['reminder_4_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold text-muted">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_4" id="metaTpl4" value="<?php echo htmlspecialchars($settings['meta_template_4'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl4" title="Fetch Templates"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Coupon Discount (%)</label>
                            <input type="number" step="0.1" class="form-control" name="coupon_discount_percent" value="<?php echo htmlspecialchars($settings['coupon_discount_percent'] ?? '10'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Coupon Validity (Hours)</label>
                            <input type="number" class="form-control" name="coupon_validity_hours" value="<?php echo htmlspecialchars($settings['coupon_validity_hours'] ?? '48'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Auto-Expire Carts (Days)</label>
                            <input type="number" class="form-control" name="auto_expire_days" value="<?php echo htmlspecialchars($settings['auto_expire_days'] ?? '7'); ?>">
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Meta Template Language Code</label>
                            <input type="text" class="form-control" name="meta_template_lang" id="metaTplLang" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en'); ?>">
                            <small class="text-muted">Language code for all Meta templates (e.g. 'en').</small>
                            <div id="tplSyncStatus" class="small mt-2 d-none"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cron Secret Key (Security Key)</label>
                            <input type="text" class="form-control" name="cron_secret_key" id="cronSecretKey" value="<?php echo htmlspecialchars($settings['cron_secret_key'] ?? 'sagar_cart_recovery_cron_secret'); ?>">
                            <small class="text-muted">Secret key for URL/HTTP Cron Job access.</small>
                        </div>
                    </div>

                    <!-- Hostinger Cron Setup Instructions -->
                    <div class="card border-0 bg-white border-start border-warning border-4 shadow-sm rounded-3 my-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-clock text-warning me-2"></i> Hostinger Cron Job Setup Guide (Auto Reminders)</h6>
                            <p class="small text-muted mb-3">Customer ko automatic WhatsApp reminders bejne ke liye Hostinger cPanel -> <strong>Cron Jobs</strong> me niche diya gaya command add karein:</p>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Option 1: PHP CLI Command (Exact Hostinger Server Path):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace bg-light" readonly id="cronCmdCli" value="/usr/bin/php /home/u902894566/domains/sagarstarters.com/public_html/cron/cart_abandonment.php">
                                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText($('#cronCmdCli').val()); alert('CLI Command Copied!');"><i class="fas fa-copy me-1"></i> Copy</button>
                                </div>
                                <small class="text-success mt-1 d-block"><i class="fas fa-check-circle me-1"></i> Exact path on Hostinger: <code>/home/u902894566/domains/sagarstarters.com/public_html/cron/cart_abandonment.php</code></small>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Option 2: Custom / URL Cron Command (Select 'Custom' in Hostinger):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace bg-light" readonly id="cronUrlHttp" value="curl -s &quot;https://sagarstarters.com/cron/cart_abandonment.php?key=<?php echo htmlspecialchars($settings['cron_secret_key'] ?? 'sagar_cart_recovery_cron_secret'); ?>&quot;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText($('#cronUrlHttp').val()); alert('Custom Cron Command Copied!');"><i class="fas fa-copy me-1"></i> Copy</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="metaTemplatesList" class="mb-4 d-none p-3 border rounded-3 bg-white shadow-sm overflow-auto" style="max-height: 250px;">
                        <h6 class="fw-bold mb-2">Select Approved Template</h6>
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Language</th>
                                    <th>Category</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tplTableBody"></tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-success mt-2" onclick="saveSettings()">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Filter Bar & Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus" onchange="loadCarts(1)">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="converted">Converted</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchKeyword" placeholder="Search name or phone..." onkeypress="if(event.key==='Enter') loadCarts(1)">
                        <button class="btn btn-outline-secondary" type="button" onclick="loadCarts(1)"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="col-md-5 text-end">
                    <button class="btn btn-light border" onclick="refreshTableAndStats()"><i class="fas fa-sync-alt me-1"></i> Refresh</button>
                    <button class="btn btn-warning text-dark border ms-2" onclick="triggerCronNow()"><i class="fas fa-paper-plane me-1"></i> Run Auto Reminders Now</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Customer</th>
                            <th>Cart Items</th>
                            <th>Cart Value</th>
                            <th>Abandoned</th>
                            <th>Reminders</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cartsTableBody">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top py-3" id="paginationContainer">
            <!-- Pagination -->
        </div>
    </div>

</div>

<script>
function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const date = new Date(dateStr.replace(' ', 'T'));
    const seconds = Math.floor((now - date) / 1000);
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

let currentPage = 1;

function loadCarts(page = 1) {
    currentPage = page;
    const status = $('#filterStatus').val();
    const search = $('#searchKeyword').val();
    
    $('#cartsTableBody').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></td></tr>');
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        data: { action: 'get_carts', status: status, search: search, page: page },
        dataType: 'json',
        success: function(response) {
            if(response.success && response.data && response.data.carts) {
                renderCartsTable(response.data.carts.data);
                renderPagination(response.data.carts.total_pages, page);
            } else {
                $('#cartsTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">No abandoned carts found.</td></tr>');
                $('#paginationContainer').empty();
            }
        },
        error: function() {
            $('#cartsTableBody').html('<tr><td colspan="7" class="text-center py-4 text-danger">Error loading data.</td></tr>');
            $('#paginationContainer').empty();
        }
    });
}

function renderCartsTable(carts) {
    let html = '';
    const currency = '<?php echo isset($global_currency) ? htmlspecialchars($global_currency) : '₹'; ?>';
    
    if(!carts || carts.length === 0) {
        html = '<tr><td colspan="7" class="text-center py-4 text-muted">No abandoned carts found.</td></tr>';
    } else {
        carts.forEach(cart => {
            const customerName = cart.customer_name || 'Guest';
            const customerPhone = cart.customer_phone || 'N/A';
            let items = cart.product_names || '';
            if(items.length > 50) items = items.substring(0, 47) + '...';
            
            const cartTotal = cart.cart_total || 0;
            const abandonedTime = timeAgo(cart.created_at);
            
            const step = parseInt(cart.reminder_step || 0);
            let remindersHtml = '<div class="d-flex align-items-center">';
            for(let i=1; i<=4; i++) {
                remindersHtml += `<span class="reminder-dot ${i <= step ? 'sent' : 'pending'}" title="Reminder ${i}"></span>`;
            }
            remindersHtml += `<span class="small text-muted ms-2">${step}/4</span></div>`;
            
            let statusBadge = '';
            if (cart.status === 'active') statusBadge = '<span class="badge bg-warning text-dark">Active</span>';
            else if (cart.status === 'converted') statusBadge = '<span class="badge bg-success">Converted</span>';
            else if (cart.status === 'expired') statusBadge = '<span class="badge bg-secondary">Expired</span>';
            else statusBadge = `<span class="badge bg-secondary">${cart.status}</span>`;
            
            html += `
                <tr>
                    <td class="ps-3">
                        <div class="fw-bold">${customerName}</div>
                        <div class="small text-muted">${customerPhone}</div>
                    </td>
                    <td><span title="${cart.product_names || ''}">${items}</span></td>
                    <td>${currency}${cartTotal}</td>
                    <td>${abandonedTime}</td>
                    <td>${remindersHtml}</td>
                    <td>${statusBadge}</td>
                    <td class="pe-3 text-end text-nowrap">
                        <button class="btn btn-sm btn-primary me-1 btn-reminder" onclick="sendReminder(${cart.id}, this)" title="Send next reminder"><i class="fas fa-paper-plane"></i></button>
                        ${cart.status === 'active' ? `
                            <button class="btn btn-sm btn-warning me-1 btn-expire" onclick="markExpired(${cart.id}, this)" title="Mark as expired"><i class="fas fa-times text-dark"></i></button>
                        ` : ''}
                        <button class="btn btn-sm btn-danger btn-delete" onclick="deleteCart(${cart.id}, this)" title="Delete cart"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });
    }
    $('#cartsTableBody').html(html);
}

function renderPagination(totalPages, currentPage) {
    if(!totalPages || totalPages <= 1) {
        $('#paginationContainer').empty();
        return;
    }
    
    let html = '<nav><ul class="pagination justify-content-center mb-0">';
    html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${currentPage - 1})">Previous</a></li>`;
    
    for(let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${i})">${i}</a></li>`;
    }
    
    html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${currentPage + 1})">Next</a></li>`;
    html += '</ul></nav>';
    
    $('#paginationContainer').html(html);
}

function sendReminder(cartId, btn) {
    const $btn = $(btn);
    const originalHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'send_reminder', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                if(res.link) {
                    window.open(res.link, '_blank');
                    alert('WhatsApp Web opened in new tab. Please send the message manually.');
                } else {
                    alert(res.message || 'Reminder sent successfully!');
                }
                refreshTableAndStats();
            } else {
                alert(res.error || res.message || 'Error sending reminder');
                $btn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function() {
            alert('Request failed');
            $btn.html(originalHtml).prop('disabled', false);
        }
    });
}

function markExpired(cartId, btn) {
    if(!confirm('Are you sure you want to mark this cart as expired? No further reminders will be sent.')) return;
    
    const $btn = $(btn);
    const originalHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'mark_expired', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                refreshTableAndStats();
            } else {
                alert(res.message || 'Error marking as expired');
                $btn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function() {
            alert('Request failed');
            $btn.html(originalHtml).prop('disabled', false);
        }
    });
}

function deleteCart(cartId, btn) {
    if(!confirm('Are you sure you want to delete this cart? This action cannot be undone.')) return;
    
    const $btn = $(btn);
    const originalHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'delete_cart', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                refreshTableAndStats();
            } else {
                alert(res.message || 'Error deleting cart');
                $btn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function() {
            alert('Request failed');
            $btn.html(originalHtml).prop('disabled', false);
        }
    });
}

function saveSettings() {
    // Collect data properly to handle checkbox
    const formData = new FormData(document.getElementById('settingsForm'));
    // If switch is not checked, it won't be in formData, so we should handle it
    if (!document.getElementById('is_enabled').checked) {
        formData.append('is_enabled', '0');
    }
    formData.append('action', 'save_settings');
    
    const data = new URLSearchParams(formData).toString();
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                alert('Settings saved successfully!');
            } else {
                alert(res.message || 'Error saving settings');
            }
        },
        error: function() {
            alert('Request failed');
        }
    });
}

function refreshStats() {
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        data: { action: 'get_stats' },
        dataType: 'json',
        success: function(res) {
            if(res.success && res.data) {
                $('#stat_active_carts').text(res.data.active_carts || 0);
                $('#stat_recovery_rate').text((res.data.recovery_rate || 0) + '%');
                const currency = '<?php echo isset($global_currency) ? htmlspecialchars($global_currency) : '₹'; ?>';
                $('#stat_lost_value').text(currency + (res.data.total_lost_value || 0));
                $('#stat_pending').text(res.data.pending_reminders || 0);
            }
        }
    });
}

function refreshTableAndStats() {
    loadCarts(currentPage);
    refreshStats();
}

$(document).ready(function() {
    loadCarts(1);
});
// Sync Templates Logic
let currentTplTarget = null;
document.addEventListener('DOMContentLoaded', function() {
    const btnSyncs = document.querySelectorAll('.btn-sync-tpl');
    const tplStatus = document.getElementById('tplSyncStatus');
    const tplList = document.getElementById('metaTemplatesList');
    const tplTableBody = document.getElementById('tplTableBody');

    btnSyncs.forEach(btn => {
        btn.addEventListener('click', function() {
            currentTplTarget = this.getAttribute('data-target');
            
            // Disable all fetch buttons temporarily
            btnSyncs.forEach(b => { b.disabled = true; });
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            tplStatus.className = 'small mt-2 fw-bold text-info';
            tplStatus.innerText = 'Connecting to Meta...';
            tplStatus.classList.remove('d-none');
            tplList.classList.add('d-none');

            fetch('ajax_sync_meta_templates.php?waba_id=')
                .then(res => res.json())
                .then(data => {
                    btnSyncs.forEach(b => { b.disabled = false; });
                    this.innerHTML = originalHtml;

                    if (data.error) {
                        tplStatus.className = 'small mt-2 fw-bold text-danger';
                        tplStatus.innerText = 'Error: ' + data.error;
                    } else if (data.templates && data.templates.length > 0) {
                        tplStatus.className = 'small mt-2 fw-bold text-success';
                        tplStatus.innerText = 'Templates fetched! Select one below for ' + currentTplTarget + '.';
                        
                        tplTableBody.innerHTML = '';
                        data.templates.forEach(tpl => {
                            const row = `
                                <tr>
                                    <td class="fw-bold fs-7">${tpl.name}</td>
                                    <td class="fs-7">${tpl.language}</td>
                                    <td class="fs-7"><span class="badge bg-light text-dark">${tpl.category}</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary py-1 px-2" onclick="selectTemplate('${tpl.name}', '${tpl.language}')">Select</button>
                                    </td>
                                </tr>
                            `;
                            tplTableBody.insertAdjacentHTML('beforeend', row);
                        });
                        tplList.classList.remove('d-none');
                    } else {
                        tplStatus.className = 'small mt-2 fw-bold text-warning';
                        tplStatus.innerText = 'No approved templates found.';
                    }
                })
                .catch(err => {
                    btnSyncs.forEach(b => { b.disabled = false; });
                    this.innerHTML = originalHtml;
                    tplStatus.className = 'small mt-2 fw-bold text-danger';
                    tplStatus.innerText = 'Network error: ' + err.message;
                });
        });
    });
});

function selectTemplate(name, lang) {
    if (currentTplTarget) {
        document.getElementById(currentTplTarget).value = name;
        document.getElementById('metaTplLang').value = lang;
        document.getElementById('metaTemplatesList').classList.add('d-none');
        document.getElementById('tplSyncStatus').className = 'small mt-2 fw-bold text-success';
        document.getElementById('tplSyncStatus').innerText = 'Template selected: ' + name;
    }
}

function triggerCronNow() {
    if (!confirm('Run automated cart recovery check right now?')) return;
    
    const btn = event.currentTarget;
    const originalText = $(btn).html();
    $(btn).html('<i class="fas fa-spinner fa-spin me-1"></i> Running...').prop('disabled', true);
    
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'trigger_cron' },
        dataType: 'json',
        success: function(res) {
            $(btn).html(originalText).prop('disabled', false);
            if (res.success) {
                alert('Auto Reminder Executed Successfully!\n' + (res.message || ('Processed: ' + (res.processed || 0))));
                refreshTableAndStats();
            } else {
                alert('Error running auto reminder: ' + (res.error || 'Unknown error'));
            }
        },
        error: function(err) {
            $(btn).html(originalText).prop('disabled', false);
            alert('Request failed: ' + err.statusText);
        }
    });
}
</script>

<?php include 'admin_footer.php'; ?>
