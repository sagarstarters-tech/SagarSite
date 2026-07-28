<?php
/**
 * WAPI SaaS - Subscription / Plan Management for Users
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

// Get available plans
$plans = $db->fetchAll("SELECT p.*, GROUP_CONCAT(pf.feature_text, '|||', pf.is_included ORDER BY pf.sort_order SEPARATOR ';;;') as features_list FROM plans p LEFT JOIN plan_features pf ON p.id = pf.plan_id WHERE p.is_active = 1 GROUP BY p.id ORDER BY p.sort_order ASC");

// Latest subscription
$latestSub = $db->fetch("SELECT s.*, p.name as plan_name FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 1", [$userId]);

$currentSub = null;
$expiredSub = null;

if ($latestSub) {
    if ($latestSub['status'] === 'active' && strtotime($latestSub['expires_at']) > time()) {
        $currentSub = $latestSub;
    } else {
        $expiredSub = $latestSub;
    }
}

// Payment history
$payments = $db->fetchAll("SELECT p.*, pl.name as plan_name FROM payments p LEFT JOIN subscriptions s ON p.subscription_id = s.id LEFT JOIN plans pl ON s.plan_id = pl.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 10", [$userId]);

$pageTitle = 'Subscription';
$extraCss = [asset('assets/css/dashboard.css')];
$extraJs = [asset('assets/js/admin.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Subscription</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Subscription</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?>"><?= e($flash['message']); ?></div>
        <?php endif; ?>

        <!-- Current Plan -->
        <?php if ($currentSub): ?>
        <div class="card mb-4" style="border-radius: var(--border-radius); border-left: 4px solid var(--primary);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold mb-1">Current Plan: <span class="text-primary"><?= e($currentSub['plan_name']); ?></span></h5>
                        <p class="text-muted mb-0">Expires: <?= formatDate($currentSub['expires_at']); ?> &bull; Status: <span class="status-badge status-active">Active</span></p>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold" style="font-size: 1.25rem;"><?= formatCurrency($currentSub['amount']); ?></div>
                        <small class="text-muted">/ <?= $currentSub['billing_cycle']; ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($expiredSub): ?>
        <div class="card mb-4" style="border-radius: var(--border-radius); border-left: 4px solid var(--danger);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-danger">Subscription Expired</h5>
                        <p class="text-muted mb-0">Your <strong><?= e($expiredSub['plan_name']); ?></strong> plan expired on <?= formatDate($expiredSub['expires_at']); ?>.</p>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-danger btn-razorpay-renew" 
                                data-plan-id="<?= $expiredSub['plan_id']; ?>" 
                                data-plan-name="<?= e($expiredSub['plan_name']); ?>"
                                onclick="document.querySelector('.btn-razorpay[data-plan-id=\'<?= $expiredSub['plan_id']; ?>\']').click();">
                            <i class="bi bi-arrow-repeat"></i> Renew Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-4 text-center">
            <h5 class="fw-bold mb-3"><?= ($currentSub || $expiredSub) ? 'Upgrade / Renew Plan' : 'Choose a Plan'; ?></h5>
            
            <!-- Pricing Toggle -->
            <div class="pricing-toggle" style="background: var(--bg-secondary); padding: 8px; border-radius: 50px; display: inline-flex; align-items: center; gap: 15px; cursor: pointer; border: 1px solid rgba(0,0,0,0.05);">
                <span class="active" id="monthlyLabel" style="font-size: 1rem; font-weight: 800; padding: 5px 20px; border-radius: 20px;">Monthly</span>
                <div class="toggle-switch" id="pricingToggle" style="width: 50px; height: 26px; background: var(--primary); border-radius: 20px; position: relative;">
                    <div class="dot" style="width: 18px; height: 18px; background: white; border-radius: 50%; position: absolute; top: 4px; left: 4px; transition: all 0.3s ease;"></div>
                </div>
                <span id="yearlyLabel" style="font-size: 1rem; font-weight: 800; padding: 5px 20px; border-radius: 20px; color: var(--text-muted);">Yearly</span>
                <span class="badge bg-success-soft text-success" style="font-size: 0.8rem; background: #e6f7ef; padding: 6px 12px; border-radius: 10px; font-weight: 700;">Save 17%</span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <?php foreach ($plans as $plan): 
                $planFeatures = [];
                if (!empty($plan['features_list'])) {
                    $featureItems = explode(';;;', $plan['features_list']);
                    foreach ($featureItems as $item) {
                        $parts = explode('|||', $item);
                        if (count($parts) === 2) $planFeatures[] = ['text' => $parts[0], 'included' => $parts[1]];
                    }
                }
                $isCurrentPlan = $currentSub && $currentSub['plan_id'] == $plan['id'];
                $isExpiredPlan = $expiredSub && $expiredSub['plan_id'] == $plan['id'];
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card <?= $plan['is_popular'] ? 'popular' : ''; ?> <?= $isCurrentPlan ? '' : ''; ?>" style="text-align:left;">
                    <?php if ($plan['is_popular']): ?><div class="pricing-badge">Most Popular</div><?php endif; ?>
                    <?php if ($isCurrentPlan): ?><div style="position:absolute;top:10px;right:15px;"><span class="status-badge status-active">Current</span></div><?php endif; ?>
                    <?php if ($isExpiredPlan): ?><div style="position:absolute;top:10px;right:15px;"><span class="status-badge status-expired" style="background:#fce8e8;color:#d93025;">Expired</span></div><?php endif; ?>
                    
                    <div class="fw-bold mb-1" style="color: <?= e($plan['badge_color']); ?>; font-size: 1.125rem;"><?= e($plan['name']); ?></div>
                    <p class="text-muted mb-3" style="font-size: 0.8125rem;"><?= e($plan['description']); ?></p>
                    
                    <div class="mb-3 pricing-amount">
                        <span class="fw-bold price-value" style="font-size: 1.75rem;" 
                              data-monthly="<?= e($plan['monthly_price']); ?>" 
                              data-yearly="<?= e($plan['yearly_price']); ?>">
                            <?= formatCurrency($plan['monthly_price']); ?>
                        </span>
                        <span class="text-muted period-label" style="font-size: 1.75rem;">/month</span>
                    </div>

                    <ul class="pricing-features" style="margin-bottom: 1.5rem;">
                        <?php 
                        // Core platform features mapping
                        $coreFeatures = [
                            'chatbot_enabled' => 'Chatbot',
                            'bulk_messaging' => 'Bulk Messaging',
                            'webhook_enabled' => 'Webhook Support',
                            'analytics_enabled' => 'Advanced Analytics',
                            'priority_support' => 'Priority Support'
                        ];

                        $coreLabelsLower = array_map(function($label) {
                            return strtolower(trim(str_replace([' ', '-'], '', $label)));
                        }, array_values($coreFeatures));

                        // Filter out manual core features from the list to avoid conflicts with toggles
                        $customFeatures = array_filter($planFeatures, function($pf) use ($coreLabelsLower) {
                            $text = strtolower(trim(str_replace([' ', '-'], '', $pf['text'])));
                            return !in_array($text, $coreLabelsLower);
                        });

                        // 1. Show non-core custom features first
                        foreach ($customFeatures as $pf): 
                        ?>
                        <li class="<?= $pf['included'] == '0' ? 'disabled' : ''; ?>">
                            <i class="bi <?= $pf['included'] == '1' ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                            <?= e($pf['text']); ?>
                        </li>
                        <?php endforeach; ?>

                        <?php 
                        // 2. Show core toggled features from the plans table columns
                        foreach ($coreFeatures as $field => $label): 
                            if (isset($plan[$field])):
                                $isIncluded = ($plan[$field] == '1');
                        ?>
                        <li class="<?= !$isIncluded ? 'disabled' : ''; ?>">
                            <i class="bi <?= $isIncluded ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                            <?= e($label); ?>
                        </li>
                        <?php endif; endforeach; ?>
                    </ul>

                    <?php if ($isCurrentPlan): ?>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary w-100" disabled>Current Plan</button>
                            <button class="btn btn-primary w-100 btn-razorpay" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-arrow-repeat"></i> Renew Early
                            </button>
                            <?php if ($settings->get('payment_method_manual_enabled') == '1'): ?>
                            <button class="btn btn-outline-success w-100 btn-manual-upi" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-phone"></i> Renew via UPI
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($isExpiredPlan): ?>
                        <div class="d-grid gap-2">
                            <button class="btn btn-danger w-100 btn-razorpay" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-arrow-repeat"></i> Renew Plan
                            </button>
                            <?php if ($settings->get('payment_method_manual_enabled') == '1'): ?>
                            <button class="btn btn-outline-danger w-100 btn-manual-upi" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-phone"></i> Renew via UPI
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($plan['monthly_price'] == 0): ?>
                        <button class="btn btn-outline-primary w-100" onclick="activateFreePlan(<?= $plan['id']; ?>)">Activate</button>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary w-100 btn-razorpay" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-credit-card"></i> Pay via Razorpay
                            </button>
                            <?php if ($settings->get('payment_method_manual_enabled') == '1'): ?>
                            <button class="btn btn-outline-success w-100 btn-manual-upi" 
                                    data-plan-id="<?= $plan['id']; ?>" 
                                    data-plan-name="<?= e($plan['name']); ?>">
                                <i class="bi bi-phone"></i> Pay via UPI (PhonePe/GPay)
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Payment History -->
        <div class="data-table">
            <div class="data-table-header"><h5 class="data-table-title mb-0">Payment History</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Plan</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No payments yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($payment['plan_name'] ?? '-'); ?></td>
                            <td class="fw-bold"><?= formatCurrency($payment['amount']); ?></td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= ucfirst($payment['payment_method'] ?? 'Razorpay'); ?></span></td>
                            <td><span class="status-badge status-<?= $payment['status'] === 'success' ? 'active' : $payment['status']; ?>"><?= ucfirst($payment['status']); ?></span></td>
                            <td style="font-size: 0.8125rem; color: var(--text-muted);"><?= formatDate($payment['created_at']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
// Pricing Toggle Logic
let billingCycle = 'monthly';

document.getElementById('pricingToggle').addEventListener('click', function() {
    const dot = this.querySelector('.dot');
    const monthlyLabel = document.getElementById('monthlyLabel');
    const yearlyLabel = document.getElementById('yearlyLabel');
    
    if (billingCycle === 'monthly') {
        billingCycle = 'yearly';
        dot.style.left = '28px';
        yearlyLabel.classList.add('active');
        yearlyLabel.style.color = 'var(--text-primary)';
        monthlyLabel.classList.remove('active');
        monthlyLabel.style.color = 'var(--text-muted)';
        updatePrices('yearly');
    } else {
        billingCycle = 'monthly';
        dot.style.left = '4px';
        monthlyLabel.classList.add('active');
        monthlyLabel.style.color = 'var(--text-primary)';
        yearlyLabel.classList.remove('active');
        yearlyLabel.style.color = 'var(--text-muted)';
        updatePrices('monthly');
    }
});

function updatePrices(period) {
    document.querySelectorAll('.price-value').forEach(el => {
        const val = period === 'monthly' ? el.getAttribute('data-monthly') : el.getAttribute('data-yearly');
        el.innerText = '₹' + parseFloat(val).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    });
    document.querySelectorAll('.period-label').forEach(el => {
        el.innerText = period === 'monthly' ? '/month' : '/year';
    });
}

// Payment Click Handlers
document.querySelectorAll('.btn-razorpay').forEach(btn => {
    btn.addEventListener('click', function() {
        const planId = this.getAttribute('data-plan-id');
        const planName = this.getAttribute('data-plan-name');
        const card = this.closest('.pricing-card');
        const priceEl = card.querySelector('.price-value');
        const amount = billingCycle === 'monthly' ? priceEl.getAttribute('data-monthly') : priceEl.getAttribute('data-yearly');
        
        initPayment(planId, planName, amount);
    });
});

document.querySelectorAll('.btn-manual-upi').forEach(btn => {
    btn.addEventListener('click', function() {
        const planId = this.getAttribute('data-plan-id');
        const planName = this.getAttribute('data-plan-name');
        const card = this.closest('.pricing-card');
        const priceEl = card.querySelector('.price-value');
        const amount = billingCycle === 'monthly' ? priceEl.getAttribute('data-monthly') : priceEl.getAttribute('data-yearly');
        
        initManualUPI(planId, planName, amount);
    });
});

function initPayment(planId, planName, amount) {
    const razorpayKey = '<?= e($settings->get('razorpay_key_id', '')); ?>';
    if (!razorpayKey) {
        alert('Razorpay Key is not configured. Please go to Admin -> Settings -> Payment and set your Key ID.');
        return;
    }

    const options = {
        key: razorpayKey,
        amount: Math.round(amount * 100), // Razorpay takes amount in paise
        currency: 'INR',
        name: '<?= e($settings->get('site_name', 'WAPI')); ?>',
        description: planName + ' Plan (' + billingCycle + ') Subscription',
        handler: function(response) {
            // Verify payment on server
            window.location.href = '<?= baseUrl('api/verify-payment.php'); ?>?payment_id=' + response.razorpay_payment_id + '&plan_id=' + planId + '&cycle=' + billingCycle;
        },
        prefill: {
            name: '<?= e($_SESSION['user_name'] ?? ''); ?>',
            email: '<?= e($_SESSION['user_email'] ?? ''); ?>'
        },
        theme: { color: '#6c63ff' }
    };
    
    const rzp = new Razorpay(options);
    rzp.open();
}

function initManualUPI(planId, planName, amount) {
    const upiId = '<?= e($settings->get('upi_id', '')); ?>';
    const upiName = '<?= e($settings->get('upi_name', 'WAPI')); ?>';
    
    if (!upiId) {
        alert('UPI ID is not configured. Please contact admin.');
        return;
    }

    const upiLink = `upi://pay?pa=${upiId}&pn=${encodeURIComponent(upiName)}&am=${amount}&cu=INR&tn=Plan_${planName}_${billingCycle}`;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiLink)}`;
    
    // Set details in modal
    document.getElementById('manualUpiId').innerText = upiId;
    document.getElementById('manualUpiName').innerText = upiName;
    document.getElementById('manualUpiAmount').innerText = '₹' + amount;
    document.getElementById('manualUpiQr').src = qrUrl;
    document.getElementById('manualUpiPlanId').value = planId;
    document.getElementById('manualUpiCycle').value = billingCycle;
    
    // Deep link for mobile
    document.getElementById('upiDeepLink').href = upiLink;

    const modal = new bootstrap.Modal(document.getElementById('manualUpiModal'));
    modal.show();
}

function activateFreePlan(planId) {
    if (confirm('Activate the 14 Days Free Trial?')) {
        window.location.href = '<?= baseUrl('api/activate-plan.php'); ?>?plan_id=' + planId;
    }
}
</script>

<!-- Manual UPI Modal -->
<div class="modal fade" id="manualUpiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-phone-vibrate"></i> Pay via UPI (Manual)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <img id="manualUpiQr" src="" alt="UPI QR Code" style="width: 200px; height: 200px; border: 4px solid #f8f9fa; border-radius: 10px;">
                </div>
                
                <div class="mb-4">
                    <p class="text-muted small mb-1">Scan QR or Pay to UPI ID:</p>
                    <h5 class="fw-bold mb-0" id="manualUpiId"></h5>
                    <p class="text-muted small" id="manualUpiName"></p>
                    <div class="display-6 fw-bold text-success mb-3" id="manualUpiAmount"></div>
                    
                    <a id="upiDeepLink" href="#" class="btn btn-outline-success btn-sm w-100 mb-2 d-md-none">
                        <i class="bi bi-box-arrow-up-right"></i> Open UPI App (Mobile Only)
                    </a>
                </div>

                <hr>

                <form action="<?= baseUrl('api/submit-utr.php'); ?>" method="POST">
                    <?= CSRF::tokenField(); ?>
                    <input type="hidden" name="plan_id" id="manualUpiPlanId">
                    <input type="hidden" name="billing_cycle" id="manualUpiCycle" value="monthly">
                    <div class="text-start mb-3">
                        <label class="form-label fw-bold">Transaction ID (UTR/Reference No.)</label>
                        <input type="text" name="utr" class="form-control" placeholder="Enter 12-digit UTR No." required pattern="[0-9A-Za-z]{8,}">
                        <div class="form-text small">After payment, copy & paste the Transaction ID from your app.</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Submit Payment Proof</button>
                    <p class="text-muted extra-small mt-3">Admin will verify your payment and activate the plan manually (Takes 2-12 hours).</p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
