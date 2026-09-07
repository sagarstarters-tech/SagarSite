<?php
include 'admin_header.php';

// Fetch Statistics
$users_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'] ?? 0;
$products_count = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'] ?? 0;
$orders_count = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'] ?? 0;
$sales_total = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE status != 'cancelled'")->fetch_assoc()['s'] ?? 0;

// Low Stock Count Alert
$low_stock_count = 0;
try {
    $ls_res = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock <= 5");
    if ($ls_res) $low_stock_count = intval($ls_res->fetch_assoc()['c'] ?? 0);
} catch (\Throwable $e) {}

// Abandoned Cart Active Count
$active_carts_count = 0;
try {
    $ac_res = $conn->query("SELECT COUNT(*) as c FROM abandoned_carts WHERE status = 'active'");
    if ($ac_res) $active_carts_count = intval($ac_res->fetch_assoc()['c'] ?? 0);
} catch (\Throwable $e) {}

// Sales Data for Chart (Last 7 Days)
$labels = [];
$data = [];
$total_7day_sales = 0;
$max_single_day = 0;

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M d', strtotime($date));
    $sum = floatval($conn->query("SELECT SUM(total_amount) as s FROM orders WHERE DATE(created_at) = '$date' AND status != 'cancelled'")->fetch_assoc()['s'] ?? 0);
    $data[] = $sum;
    $total_7day_sales += $sum;
    if ($sum > $max_single_day) $max_single_day = $sum;
}

$currency = isset($global_currency) ? htmlspecialchars($global_currency) : '₹';
$admin_name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin';
$site_version = $site_version ?? (!empty($global_settings['site_version']) ? $global_settings['site_version'] : (defined('APP_VERSION') ? APP_VERSION : 'v2.5.0'));
?>

<style>
/* ══════════════════════════════════════════════════════════════════
   EXECUTIVE ADMIN DASHBOARD - DESIGN SYSTEM
   ══════════════════════════════════════════════════════════════════ */
:root {
    --dash-border: #e2e8f0;
    --dash-card-bg: #ffffff;
}

.dash-wrapper {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1e293b;
}

/* Hero Header */
.dash-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 28px 32px;
    color: #ffffff;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.dash-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* Stat Cards */
.dash-stat-card {
    background: #ffffff;
    border: 1px solid var(--dash-border);
    border-radius: 18px;
    padding: 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    position: relative;
    overflow: hidden;
}
.dash-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    border-color: #cbd5e1;
}

.dash-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.dash-icon-blue { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #2563eb; }
.dash-icon-emerald { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #059669; }
.dash-icon-amber { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); color: #d97706; }
.dash-icon-purple { background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #7c3aed; }

.dash-stat-val {
    font-size: 1.85rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.02em;
    color: #0f172a;
}
.dash-stat-lbl {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    margin-bottom: 4px;
}

/* Action Bar Cards */
.dash-action-btn {
    background: #ffffff;
    border: 1px solid var(--dash-border);
    border-radius: 12px;
    padding: 12px 18px;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #334155;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.dash-action-btn:hover {
    background: #f8fafc;
    color: #1e293b;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

/* Card Container */
.dash-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid var(--dash-border);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

/* Order List Item */
.dash-order-item {
    padding: 14px 18px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    margin-bottom: 10px;
}
.dash-order-item:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    transform: translateX(4px);
}
.dash-order-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.dash-btn-white,
a.dash-btn-white {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18) !important;
    transition: all 0.2s ease !important;
}
.dash-btn-white *,
a.dash-btn-white * {
    background: transparent !important;
    background-color: transparent !important;
    color: #0f172a !important;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    text-shadow: none !important;
}
.dash-btn-white::before,
.dash-btn-white::after,
a.dash-btn-white::before,
a.dash-btn-white::after {
    display: none !important;
    content: none !important;
}
.dash-btn-white:hover,
a.dash-btn-white:hover {
    background-color: #f1f5f9 !important;
    color: #2563eb !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25) !important;
}
.dash-btn-white:hover * {
    color: #2563eb !important;
}
</style>

<div class="container-fluid py-4 dash-wrapper">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  1. HERO WELCOME HEADER                                     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="dash-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-store me-1"></i> Admin Command Center
                    </span>
                    <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-1 small d-inline-flex align-items-center gap-1.5 shadow-sm" style="cursor: pointer; transition: all 0.2s;" data-mdb-toggle="modal" data-mdb-target="#editVersionModal" title="Click to change version / Auto Change Version">
                        <i class="fas fa-code-branch text-warning me-1"></i>
                        <span>Version <strong class="text-white" id="heroVersionText"><?php echo htmlspecialchars($site_version); ?></strong></span>
                        <?php if (!empty($auto_version_enabled)): ?>
                            <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 rounded-pill px-1.5 py-0.5 ms-1" style="font-size: 0.62rem;" title="Auto Change Version Active">Auto</span>
                        <?php endif; ?>
                        <i class="fas fa-pencil-alt text-white-50 ms-1" style="font-size: 0.65rem;"></i>
                    </span>
                    <span class="text-white-50 small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?></span>
                </div>
                <h2 class="fw-bold mb-1 text-white fs-3">Welcome back, <?php echo $admin_name; ?>! 👋</h2>
                <p class="text-white-50 mb-0 small">Here is what is happening across your store today.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="manage_products.php?action=add" class="btn btn-primary px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold text-white">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Product</span>
                </a>
                <a href="../index.php" target="_blank" class="btn dash-btn-white px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                    <i class="fas fa-external-link-alt"></i>
                    <span>View Live Store</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  2. QUICK ACTION SHORTCUT BAR                               -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-2">
        <a href="manage_orders.php" class="dash-action-btn">
            <i class="fas fa-shopping-bag text-primary"></i> Orders Hub
        </a>
        <a href="manage_products.php" class="dash-action-btn">
            <i class="fas fa-box text-success"></i> Catalog Products
        </a>
        <a href="manage_abandoned_carts.php" class="dash-action-btn">
            <i class="fas fa-shopping-cart text-warning"></i> Abandoned Recovery
            <?php if ($active_carts_count > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill"><?php echo $active_carts_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_users.php" class="dash-action-btn">
            <i class="fas fa-users text-info"></i> Customers
        </a>
        <a href="manage_settings.php" class="dash-action-btn">
            <i class="fas fa-cog text-secondary"></i> Global Settings
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  3. CORE METRICS STATS OVERVIEW                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Sales Total -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Total Sales Revenue</div>
                        <div class="dash-stat-val" style="color: #059669;"><?php echo $currency; ?><?php echo number_format($sales_total, 2); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-check-circle text-success me-1"></i> Non-cancelled orders</div>
                    </div>
                    <div class="dash-icon-box dash-icon-emerald">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Total Orders</div>
                        <div class="dash-stat-val"><?php echo number_format($orders_count); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-shopping-bag text-primary me-1"></i> Orders processed</div>
                    </div>
                    <div class="dash-icon-box dash-icon-blue">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Products -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Total Products</div>
                        <div class="dash-stat-val" style="color: #d97706;"><?php echo number_format($products_count); ?></div>
                        <div class="small text-muted mt-1">
                            <?php if ($low_stock_count > 0): ?>
                                <span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i><?php echo $low_stock_count; ?> low stock</span>
                            <?php else: ?>
                                <i class="fas fa-box text-warning me-1"></i> Active in catalog
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dash-icon-box dash-icon-amber">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Active Customers</div>
                        <div class="dash-stat-val" style="color: #7c3aed;"><?php echo number_format($users_count); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-user-check text-purple me-1"></i> Registered users</div>
                    </div>
                    <div class="dash-icon-box dash-icon-purple">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  4. SALES ANALYTICS CHART & RECENT ORDERS                   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4">
        <!-- Sales Line Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-primary me-2"></i> Sales Performance Overview</h6>
                        <span class="small text-muted">Daily revenue trend for the last 7 days</span>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted fw-bold text-uppercase">7-Day Revenue</div>
                        <div class="fw-bold fs-6 text-success"><?php echo $currency; ?><?php echo number_format($total_7day_sales, 2); ?></div>
                    </div>
                </div>
                <div style="height: 320px; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Orders & Live Activity Column -->
        <div class="col-xl-4 col-lg-5">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clock text-primary me-2"></i> Recent Orders</h6>
                    <a href="manage_orders.php" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold">View All</a>
                </div>

                <?php
                $recent = $conn->query("SELECT o.id, o.total_amount, o.status, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
                if ($recent && $recent->num_rows > 0):
                ?>
                    <div class="d-flex flex-column gap-2">
                        <?php while($r = $recent->fetch_assoc()): ?>
                            <div class="dash-order-item d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="dash-order-avatar">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small mb-0">#<?php echo $r['id']; ?> - <?php echo htmlspecialchars($r['user_name']); ?></div>
                                        <?php
                                        $statusClass = 'bg-secondary';
                                        if ($r['status'] == 'pending') $statusClass = 'bg-warning text-dark';
                                        elseif ($r['status'] == 'shipped') $statusClass = 'bg-info text-white';
                                        elseif ($r['status'] == 'delivered') $statusClass = 'bg-success text-white';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="fw-bold text-dark fs-6"><?php echo $currency; ?><?php echo number_format($r['total_amount'], 2); ?></div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted opacity-25 mb-3"></i>
                        <p class="text-muted mb-0 small">No recent orders yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  5. PLATFORM & SYSTEM STATUS OVERVIEW                       -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="dash-card p-3 px-4 mt-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="dash-icon-box" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; width: 44px; height: 44px; font-size: 1.15rem; border-radius: 12px;">
                    <i class="fas fa-server"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold text-dark small"><?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's Store"); ?></span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.72rem;">
                            <i class="fas fa-check-circle me-1"></i> System Active
                        </span>
                    </div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        <span>PHP <?php echo PHP_VERSION; ?></span>
                        <span class="mx-1">&bull;</span>
                        <span>Environment: <strong class="text-capitalize"><?php echo defined('APP_ENV') ? APP_ENV : 'production'; ?></strong></span>
                        <span class="mx-1">&bull;</span>
                        <span>Timezone: <?php echo date_default_timezone_get(); ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="px-3 py-1.5 rounded-3 bg-light border text-secondary small d-flex align-items-center gap-2">
                    <i class="fas fa-code-branch text-primary"></i>
                    <span>Website Version: <strong class="text-primary fw-bold" id="dashVersionDisplay"><?php echo htmlspecialchars($site_version); ?></strong></span>
                    <?php if (!empty($auto_version_enabled)): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.72rem;" id="dashAutoBadge" title="Auto Change Version is ON">
                            <i class="fas fa-magic me-1"></i> Auto ON
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 py-1.5 d-flex align-items-center gap-1.5 shadow-sm text-white fw-semibold" data-mdb-toggle="modal" data-mdb-target="#editVersionModal" style="font-size: 0.82rem;">
                    <i class="fas fa-edit"></i> Change Version
                </button>
                <a href="manage_settings.php?tab=general" class="btn btn-sm btn-outline-secondary rounded-3 px-3 py-1.5 d-flex align-items-center gap-1.5" style="font-size: 0.82rem;">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  6. QUICK EDIT WEBSITE VERSION MODAL                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editVersionModal" tabindex="-1" aria-labelledby="editVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-0 py-3 px-4">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2 mb-0" id="editVersionModalLabel">
                    <i class="fas fa-code-branch text-primary"></i> Update Website Version & Release
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickVersionForm">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Website ka release version yahan se badlein ya Auto Change Version logic configure karein. Ye turant store headers aur admin dashboard par sync ho jayega.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Website / Release Version</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light text-secondary"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control form-control-lg fs-6 fw-bold text-primary" id="modalVersionInput" name="version" value="<?php echo htmlspecialchars($site_version); ?>" placeholder="e.g. v2.5.0" required>
                        </div>
                        <!-- Quick Bump Helper Pills -->
                        <div class="d-flex align-items-center gap-1.5 flex-wrap mt-2">
                            <span class="text-muted small me-1" style="font-size: 0.78rem;">Quick Bump:</span>
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-pill fw-semibold btn-quick-bump" data-bump-version="<?php echo htmlspecialchars($version_metadata['next_patch'] ?? 'v2.5.1'); ?>" style="font-size: 0.76rem;">
                                <i class="fas fa-arrow-up me-1"></i> +Patch (<?php echo htmlspecialchars($version_metadata['next_patch'] ?? 'v2.5.1'); ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-pill fw-semibold btn-quick-bump" data-bump-version="<?php echo htmlspecialchars($version_metadata['next_minor'] ?? 'v2.6.0'); ?>" style="font-size: 0.76rem;">
                                <i class="fas fa-level-up-alt me-1"></i> +Minor (<?php echo htmlspecialchars($version_metadata['next_minor'] ?? 'v2.6.0'); ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-pill fw-semibold btn-quick-bump" data-bump-version="<?php echo htmlspecialchars($version_metadata['next_major'] ?? 'v3.0.0'); ?>" style="font-size: 0.76rem;">
                                <i class="fas fa-rocket me-1"></i> +Major (<?php echo htmlspecialchars($version_metadata['next_major'] ?? 'v3.0.0'); ?>)
                            </button>
                        </div>
                    </div>

                    <!-- Auto Change Version Switch Box -->
                    <div class="p-3 mb-3 rounded-3 border" style="background: #f8fafc; border-left: 4px solid #10b981 !important;">
                        <div class="form-check form-switch mb-1 d-flex align-items-center">
                            <input class="form-check-input me-2" type="checkbox" role="switch" id="modalAutoVersionSwitch" name="auto_version" value="1" <?php echo !empty($auto_version_enabled) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold text-dark small" for="modalAutoVersionSwitch">
                                <i class="fas fa-magic text-success me-1"></i> Auto Change Version on Updates
                            </label>
                        </div>
                        <p class="text-muted mb-0" style="font-size: 0.77rem; line-height: 1.4; padding-left: 2.2rem;">
                            Jab bhi koi new Git commit ya code update aayega, website version automatically patch release me increase hota rahega (e.g. <code><?php echo htmlspecialchars($site_version); ?></code> &rarr; <code><?php echo htmlspecialchars($version_metadata['next_patch'] ?? 'v2.5.1'); ?></code>).
                        </p>
                    </div>

                    <!-- Export Ready-to-Sell Package Auto-Sync Box -->
                    <?php 
                    $export_meta = class_exists('VersionManager') ? VersionManager::getExportPackageMetadata() : null; 
                    ?>
                    <div class="p-3 mb-3 rounded-3 border" style="background: #f0fdf4; border-left: 4px solid #3b82f6 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark small">
                                <i class="fas fa-box-open text-primary me-1"></i> Ready-to-Sell Package Auto-Sync
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0.5 px-2.5 rounded-pill fw-semibold shadow-0" id="btnManualSyncExport" style="font-size: 0.72rem;">
                                <i class="fas fa-sync-alt me-1"></i> Sync Package
                            </button>
                        </div>
                        <p class="text-muted mb-1.5" style="font-size: 0.76rem; line-height: 1.4;">
                            Code update ya Git commit par <code>export_ready_to_sell/</code> package auto-update ho jata hai (Local only — Hostinger push se permanently blocked).
                        </p>
                        <?php if ($export_meta): ?>
                        <div class="text-secondary small d-flex justify-content-between flex-wrap gap-1" style="font-size: 0.72rem;">
                            <span><i class="fas fa-file-archive text-primary me-1"></i> Package: <strong><?php echo htmlspecialchars($export_meta['size_mb'] ?? '48.7'); ?> MB</strong> (<?php echo intval($export_meta['total_files'] ?? 0); ?> files)</span>
                            <span><i class="far fa-clock text-primary me-1"></i> Synced: <strong><?php echo !empty($export_meta['build_time']) ? date('M d, H:i', strtotime($export_meta['build_time'])) : 'Just now'; ?></strong></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Build & Commit Metadata Info -->
                    <div class="d-flex align-items-center justify-content-between text-muted small px-1 mb-2" style="font-size: 0.75rem;">
                        <span><i class="fas fa-code-branch text-secondary me-1"></i> Commit: <code class="text-dark fw-bold"><?php echo htmlspecialchars($version_metadata['short_hash'] ?? 'abb1e58'); ?></code></span>
                        <span><i class="far fa-clock text-secondary me-1"></i> Last Bump: <?php echo !empty($version_metadata['last_bump_at']) ? date('M d, H:i', strtotime($version_metadata['last_bump_at'])) : 'Baseline'; ?></span>
                    </div>

                    <div id="versionModalAlert" class="alert d-none py-2 px-3 small rounded-3 mb-0"></div>
                </div>
                <div class="modal-footer border-0 bg-light py-2 px-4 d-flex justify-content-between">
                    <a href="manage_settings.php?tab=general" class="btn btn-link btn-sm text-decoration-none text-muted px-0">
                        <i class="fas fa-external-link-alt me-1"></i> Open Settings Page
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-3 px-3" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm text-white" id="saveVersionSubmitBtn">
                            <i class="fas fa-check me-1"></i> Save Version
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');

    // Subtle modern gradient fill
    let gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Sales',
                data: <?php echo json_encode($data); ?>,
                borderColor: '#3b82f6',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderColor: '#1d4ed8',
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: <?php echo $currency; ?>' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9',
                        borderDash: [4, 4],
                        drawBorder: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11 },
                        padding: 8,
                        callback: function(value) {
                            return '<?php echo $currency; ?>' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11 },
                        padding: 8
                    }
                }
            }
        }
    });

    // Quick bump button listeners
    document.querySelectorAll('.btn-quick-bump').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetVer = this.getAttribute('data-bump-version');
            if (targetVer) {
                const input = document.getElementById('modalVersionInput');
                if (input) {
                    input.value = targetVer;
                    input.focus();
                }
            }
        });
    });

    // Quick Version Update AJAX Handler
    const versionForm = document.getElementById('quickVersionForm');
    if (versionForm) {
        versionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('saveVersionSubmitBtn');
            const alertBox = document.getElementById('versionModalAlert');
            const input = document.getElementById('modalVersionInput');
            const autoSwitch = document.getElementById('modalAutoVersionSwitch');
            const newVersion = input.value.trim();
            const autoEnabled = autoSwitch ? (autoSwitch.checked ? '1' : '0') : '1';

            if (!newVersion) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
            alertBox.className = 'alert d-none';

            const formData = new FormData();
            formData.append('version', newVersion);
            formData.append('auto_version', autoEnabled);

            fetch('ajax_update_version.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Save Version';
                if (data.success) {
                    alertBox.className = 'alert alert-success py-2 px-3 small rounded-3 mb-0';
                    alertBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
                    
                    const heroText = document.getElementById('heroVersionText');
                    if (heroText) heroText.textContent = data.version;
                    const dashText = document.getElementById('dashVersionDisplay');
                    if (dashText) dashText.textContent = data.version;
                    const dashBadge = document.getElementById('dashAutoBadge');
                    if (dashBadge) {
                        if (autoEnabled === '1') dashBadge.classList.remove('d-none');
                        else dashBadge.classList.add('d-none');
                    }

                    setTimeout(() => {
                        const modalEl = document.getElementById('editVersionModal');
                        if (window.bootstrap && bootstrap.Modal) {
                            const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            inst.hide();
                        } else if (window.mdb && mdb.Modal) {
                            const inst = mdb.Modal.getInstance(modalEl) || new mdb.Modal(modalEl);
                            inst.hide();
                        } else if (window.jQuery) {
                            $(modalEl).modal('hide');
                        }
                        location.reload();
                    }, 700);
                } else {
                    alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                    alertBox.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Failed to update.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Save Version';
                alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Request failed. Please check connection.';
            });
        });
    }

    // Manual Sync Export Package Handler
    const syncExportBtn = document.getElementById('btnManualSyncExport');
    if (syncExportBtn) {
        syncExportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const alertBox = document.getElementById('versionModalAlert');
            syncExportBtn.disabled = true;
            syncExportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Packaging...';

            const fd = new FormData();
            fd.append('action', 'sync_export_package');

            fetch('ajax_update_version.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                syncExportBtn.disabled = false;
                if (data.success) {
                    syncExportBtn.innerHTML = '<i class="fas fa-check me-1"></i> Synced!';
                    if (alertBox) {
                        alertBox.className = 'alert alert-success py-2 px-3 small rounded-3 mb-0';
                        alertBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
                        alertBox.classList.remove('d-none');
                    }
                } else {
                    syncExportBtn.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Error';
                    if (alertBox) {
                        alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                        alertBox.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Sync failed.');
                        alertBox.classList.remove('d-none');
                    }
                }
                setTimeout(() => {
                    syncExportBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Sync Package';
                }, 3500);
            })
            .catch(err => {
                syncExportBtn.disabled = false;
                syncExportBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Sync Package';
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                    alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Network error during packaging.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }
});
</script>

<?php include 'admin_footer.php'; ?>
