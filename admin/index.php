<?php
include 'admin_header.php';

// Fetch Statistics
$users_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$products_count = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$orders_count = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$sales_total = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE status != 'cancelled'")->fetch_assoc()['s'] ?? 0;

// Sales Data for Chart (Last 7 Days)
$labels = [];
$data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M d', strtotime($date));
    $sum = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE DATE(created_at) = '$date' AND status != 'cancelled'")->fetch_assoc()['s'] ?? 0;
    $data[] = $sum;
}
?>

<style>
.stat-card {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: none;
    overflow: hidden;
    position: relative;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}
.stat-card::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    background: linear-gradient(rgba(255,255,255,0.1), rgba(255,255,255,0));
    pointer-events: none;
}
.bg-gradient-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }

.icon-circle {
    height: 3rem;
    width: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255,255,255,0.2);
    backdrop-filter: blur(5px);
}
.order-list-item {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #f8f9fa;
}
.order-list-item:hover {
    transform: translateX(5px);
    background-color: #f8f9fa;
    border-color: #e9ecef;
}
.chart-container {
    position: relative;
    height: 350px;
    width: 100%;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h3 class="h4 mb-0 text-gray-800 fw-bold"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Overview</h3>
        <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?></span>
    </div>

    <div class="row g-4 mb-5">
        <!-- Stat Cards -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card rounded-4 h-100 bg-gradient-primary text-white shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-uppercase text-white-50 mb-0 fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Users</h6>
                        <div class="icon-circle shadow-sm">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-0 display-6"><?php echo number_format($users_count); ?></h2>
                    <div class="mt-2 text-white-50 small">Active users on platform</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card rounded-4 h-100 bg-gradient-success text-white shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-uppercase text-white-50 mb-0 fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Sales</h6>
                        <div class="icon-circle shadow-sm">
                            <i class="fas fa-dollar-sign fa-lg"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-0 display-6"><?php echo isset($global_currency) ? $global_currency : '₹'; ?><?php echo number_format($sales_total, 2); ?></h2>
                    <div class="mt-2 text-white-50 small">Revenue generated</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card rounded-4 h-100 bg-gradient-warning text-white shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-uppercase text-white-50 mb-0 fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Orders</h6>
                        <div class="icon-circle shadow-sm">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-0 display-6"><?php echo number_format($orders_count); ?></h2>
                    <div class="mt-2 text-white-50 small">Orders processed</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card rounded-4 h-100 bg-gradient-info text-white shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-uppercase text-white-50 mb-0 fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Products</h6>
                        <div class="icon-circle shadow-sm">
                            <i class="fas fa-box-open fa-lg"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-0 display-6"><?php echo number_format($products_count); ?></h2>
                    <div class="mt-2 text-white-50 small">Items in catalog</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Chart -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h6 class="m-0 font-weight-bold text-primary fw-bold">Sales Overview (Last 7 Days)</h6>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary fw-bold">Recent Orders</h6>
                    <a href="manage_orders.php" class="btn btn-sm btn-light text-primary fw-bold shadow-sm">View All</a>
                </div>
                <div class="card-body p-4 pt-2">
                    <?php
                    $recent = $conn->query("SELECT o.id, o.total_amount, o.status, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
                    if ($recent && $recent->num_rows > 0):
                    ?>
                    <ul class="list-group list-group-flush">
                        <?php while($r = $recent->fetch_assoc()): ?>
                        <li class="list-group-item order-list-item px-3 py-3 rounded-3 mb-2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle p-2 me-3 shadow-sm" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-shopping-bag text-primary"></i>
                                </div>
                                <div>
                                    <p class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">#<?php echo $r['id']; ?> - <?php echo htmlspecialchars($r['user_name']); ?></p>
                                    <span class="badge bg-<?php echo $r['status'] == 'pending' ? 'warning text-dark' : ($r['status'] == 'shipped' ? 'info' : ($r['status'] == 'delivered' ? 'success' : 'secondary')); ?> px-2 py-1 mt-1 rounded-pill" style="font-size: 0.7rem;">
                                        <?php echo ucfirst($r['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <span class="fw-bold fs-6 text-dark"><?php echo isset($global_currency) ? $global_currency : '₹'; ?><?php echo number_format($r['total_amount'], 2); ?></span>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted opacity-25 mb-3"></i>
                        <p class="text-muted mb-0">No recent orders yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Create subtle gradient for chart fill
    let gradient = ctx.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(78, 115, 223, 0.4)');
    gradient.addColorStop(1, 'rgba(78, 115, 223, 0.05)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Sales',
                data: <?php echo json_encode($data); ?>,
                borderColor: '#4e73df',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#4e73df',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#4e73df',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 // Smooth curves
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#5a5c69',
                    bodyColor: '#3a3b45',
                    borderColor: '#e3e6f0',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    boxShadow: '0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15)',
                    callbacks: {
                        label: function(context) {
                            return '<?php echo isset($global_currency) ? $global_currency : "₹"; ?>' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { 
                        borderDash: [5, 5],
                        color: '#eaecf4',
                        drawBorder: false,
                        zeroLineColor: '#eaecf4'
                    },
                    ticks: {
                        color: '#858796',
                        padding: 10,
                        callback: function(value) {
                            return '<?php echo isset($global_currency) ? $global_currency : "₹"; ?>' + value.toLocaleString();
                        }
                    }
                },
                x: { 
                    grid: { 
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: '#858796',
                        padding: 10
                    }
                }
            }
        }
    });
});
</script>

<?php include 'admin_footer.php'; ?>
