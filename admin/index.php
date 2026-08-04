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
.dash-btn-outline {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    backdrop-filter: blur(4px);
    transition: all 0.2s ease;
}
.dash-btn-outline:hover, .dash-btn-outline:focus {
    background: #ffffff !important;
    color: #0f172a !important;
    border-color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.25) !important;
}
</style>

<div class="container-fluid py-4 dash-wrapper">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  1. HERO WELCOME HEADER                                     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="dash-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-store me-1"></i> Admin Command Center
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
                <a href="../index.php" target="_blank" class="btn dash-btn-outline px-3 py-2 rounded-3 d-flex align-items-center gap-2 fw-semibold">
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
});
</script>

<?php include 'admin_footer.php'; ?>
