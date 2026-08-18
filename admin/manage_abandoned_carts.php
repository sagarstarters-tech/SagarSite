<?php
include 'admin_header.php';
require_once '../includes/AbandonedCartService.php';

$abandonedCartService = new AbandonedCartService($conn);
$dashboardData = $abandonedCartService->getAdminDashboardData();
$stats = $dashboardData['stats'];
$settings = $dashboardData['settings'];
$currency = isset($global_currency) ? htmlspecialchars($global_currency) : '₹';
?>
<style>
/* ══════════════════════════════════════════════════════════════════
   ABANDONED CARTS DASHBOARD - PREMIUM DESIGN SYSTEM
   ══════════════════════════════════════════════════════════════════ */
:root {
    --ac-primary: #3b82f6;
    --ac-primary-dark: #1d4ed8;
    --ac-success: #10b981;
    --ac-warning: #f59e0b;
    --ac-danger: #ef4444;
    --ac-purple: #8b5cf6;
    --ac-card-bg: #ffffff;
    --ac-body-bg: #f8fafc;
    --ac-border-color: #e2e8f0;
}

.ac-dashboard-wrapper {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1e293b;
}

/* Header Banner */
.ac-hero-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 28px 32px;
    color: #ffffff;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.ac-hero-header::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.ac-badge-live {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(52, 211, 153, 0.3);
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ac-badge-live .pulse-dot {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: acPulse 1.6s infinite;
}
@keyframes acPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Stat Cards */
.ac-stat-card {
    background: #ffffff;
    border: 1px solid var(--ac-border-color);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    position: relative;
    overflow: hidden;
}
.ac-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    border-color: #cbd5e1;
}
.ac-icon-shape {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.ac-icon-blue { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #2563eb; }
.ac-icon-green { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #059669; }
.ac-icon-amber { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); color: #d97706; }
.ac-icon-pink { background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); color: #db2777; }

.ac-stat-value {
    font-size: 1.85rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.02em;
    color: #0f172a;
}
.ac-stat-label {
    font-size: 0.825rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 4px;
}

/* Status Filter Tabs */
.ac-filter-tabs {
    background: #f1f5f9;
    padding: 5px;
    border-radius: 12px;
    display: inline-flex;
    gap: 4px;
    border: 1px solid #e2e8f0;
}
.ac-filter-tab {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    border: none;
    background: transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ac-filter-tab:hover {
    color: #1e293b;
}
.ac-filter-tab.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}
.ac-filter-tab .badge {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 50px;
}

/* Modern Data Table */
.ac-table-container {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid var(--ac-border-color);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.ac-table {
    margin-bottom: 0;
    width: 100%;
}
.ac-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--ac-border-color);
}
.ac-table td {
    padding: 18px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.9rem;
}
.ac-table tr:last-child td {
    border-bottom: none;
}
.ac-table tr:hover td {
    background-color: #f8fafc;
}

/* Customer Avatar Circle */
.ac-avatar-circle {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
}

/* Reminder Timeline Indicator */
.ac-reminder-timeline {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ac-reminder-step {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    transition: all 0.2s ease;
}
.ac-reminder-step.done {
    background: #10b981;
    color: #ffffff;
}
.ac-reminder-step.pending {
    background: #e2e8f0;
    color: #94a3b8;
}
.ac-reminder-step.active {
    background: #f59e0b;
    color: #ffffff;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}

/* Action Buttons */
.ac-btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}
.ac-btn-wa {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.ac-btn-wa:hover {
    background: #10b981;
    color: #ffffff;
    border-color: #10b981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.ac-btn-info {
    background: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
}
.ac-btn-info:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.ac-btn-warn {
    background: #fffbeb;
    color: #d97706;
    border-color: #fde68a;
}
.ac-btn-warn:hover {
    background: #f59e0b;
    color: #ffffff;
    border-color: #f59e0b;
}
.ac-btn-del {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.ac-btn-del:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* Status Pill Badges */
.ac-status-pill {
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.775rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ac-status-active { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.ac-status-converted { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.ac-status-expired { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

/* Settings Drawer / Collapse */
.ac-settings-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid var(--ac-border-color);
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.08);
}
.ac-btn-white,
button.ac-btn-white {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18) !important;
    transition: all 0.2s ease !important;
}
.ac-btn-white *,
button.ac-btn-white * {
    background: transparent !important;
    background-color: transparent !important;
    color: #0f172a !important;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    text-shadow: none !important;
}
.ac-btn-white::before,
.ac-btn-white::after,
button.ac-btn-white::before,
button.ac-btn-white::after {
    display: none !important;
    content: none !important;
}
.ac-btn-white:hover,
button.ac-btn-white:hover {
    background-color: #f1f5f9 !important;
    color: #2563eb !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25) !important;
}
.ac-btn-white:hover * {
    color: #2563eb !important;
}

/* Refresh Button Styling */
.ac-btn-refresh,
button.ac-btn-refresh {
    background-color: #ffffff !important;
    color: #1e293b !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    padding: 7px 14px !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
    white-space: nowrap !important;
    height: 38px !important;
    cursor: pointer !important;
}
.ac-btn-refresh i,
button.ac-btn-refresh i {
    color: #2563eb !important;
    font-size: 0.95rem !important;
    transition: transform 0.3s ease !important;
}
.ac-btn-refresh:hover,
button.ac-btn-refresh:hover {
    background-color: #eff6ff !important;
    border-color: #3b82f6 !important;
    color: #1d4ed8 !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.18) !important;
    transform: translateY(-1px) !important;
}
.ac-btn-refresh:hover i,
button.ac-btn-refresh:hover i {
    color: #1d4ed8 !important;
}
.ac-btn-refresh:active,
button.ac-btn-refresh:active {
    transform: translateY(0) !important;
}
</style>

<div class="container-fluid py-4 ac-dashboard-wrapper">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  1. HERO HEADER BANNER                                      -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="ac-hero-header mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="ac-badge-live">
                        <span class="pulse-dot"></span>
                        LIVE ENGINE ACTIVE
                    </span>
                    <span class="text-white-50 small"><i class="fas fa-robot me-1"></i> WhatsApp Auto-Recovery</span>
                </div>
                <h2 class="fw-bold mb-1 text-white fs-3">Cart Abandonment Recovery Hub</h2>
                <p class="text-white-50 mb-0 small">Monitor abandoned carts, track conversion analytics, and send automated WhatsApp reminders.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-warning text-dark fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2" onclick="triggerCronNow()">
                    <i class="fas fa-bolt"></i>
                    <span>Run Reminders Now</span>
                </button>
                <button class="btn ac-btn-white px-3 py-2 rounded-3 d-flex align-items-center gap-2" type="button" data-mdb-toggle="collapse" data-mdb-target="#settingsCollapse" aria-expanded="false">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings & Templates</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  2. EXECUTIVE STATS OVERVIEW                               -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4" id="statsRow">
        <div class="col-xl-3 col-md-6">
            <div class="ac-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ac-stat-label">Active Abandoned Carts</div>
                        <div class="ac-stat-value" id="stat_active_carts"><?php echo htmlspecialchars($stats['active_carts'] ?? '0'); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-clock text-warning me-1"></i> In recovery pipeline</div>
                    </div>
                    <div class="ac-icon-shape ac-icon-blue">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ac-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ac-stat-label">Recovery Rate</div>
                        <div class="ac-stat-value" id="stat_recovery_rate"><?php echo htmlspecialchars($stats['recovery_rate'] ?? '0'); ?>%</div>
                        <div class="small text-success mt-1"><i class="fas fa-arrow-up me-1"></i> Cart conversions</div>
                    </div>
                    <div class="ac-icon-shape ac-icon-green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ac-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ac-stat-label">Total Lost Revenue</div>
                        <div class="ac-stat-value" style="color: #d97706;" id="stat_lost_value"><?php echo $currency; ?><?php echo number_format($stats['total_lost_value'] ?? 0, 2); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-rupee-sign me-1"></i> Potential recovery value</div>
                    </div>
                    <div class="ac-icon-shape ac-icon-amber">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ac-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ac-stat-label">Pending Reminders</div>
                        <div class="ac-stat-value" style="color: #db2777;" id="stat_pending"><?php echo htmlspecialchars($stats['pending_reminders'] ?? '0'); ?></div>
                        <div class="small text-muted mt-1"><i class="fas fa-paper-plane text-pink me-1"></i> Next schedule queue</div>
                    </div>
                    <div class="ac-icon-shape ac-icon-pink">
                        <i class="fas fa-bell"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  3. SETTINGS & AUTOMATION DRAWER (COLLAPSIBLE)              -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="collapse mb-4" id="settingsCollapse">
        <div class="ac-settings-card p-4">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fas fa-cogs text-primary me-2"></i> Automation & WhatsApp Settings</h5>
                    <p class="text-muted small mb-0">Configure delays, custom message templates, Meta Cloud API settings, and Cron triggers.</p>
                </div>
                <button class="btn-close" type="button" data-mdb-toggle="collapse" data-mdb-target="#settingsCollapse"></button>
            </div>

            <form id="settingsForm">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>

                <div class="form-check form-switch p-3 bg-light rounded-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>
                        <label class="form-check-label fw-bold text-dark mb-0 fs-6" for="is_enabled">Enable Cart Abandonment Auto-Recovery</label>
                        <div class="small text-muted">When active, background Cron job will trigger automated WhatsApp messages.</div>
                    </div>
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="is_enabled" name="is_enabled" <?php echo (!empty($settings['is_enabled']) && $settings['is_enabled'] != '0') ? 'checked' : ''; ?> value="1" style="width: 48px; height: 24px;">
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Reminder 1 Delay (Minutes)</label>
                        <input type="number" class="form-control rounded-3" name="reminder_1_delay" value="<?php echo htmlspecialchars($settings['reminder_1_delay'] ?? '30'); ?>">
                        <small class="text-muted">Default: 30 mins</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Reminder 2 Delay (Minutes)</label>
                        <input type="number" class="form-control rounded-3" name="reminder_2_delay" value="<?php echo htmlspecialchars($settings['reminder_2_delay'] ?? '360'); ?>">
                        <small class="text-muted">Default: 360 mins (6 hrs)</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Reminder 3 Delay (Minutes)</label>
                        <input type="number" class="form-control rounded-3" name="reminder_3_delay" value="<?php echo htmlspecialchars($settings['reminder_3_delay'] ?? '1440'); ?>">
                        <small class="text-muted">Default: 1440 mins (24 hrs)</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Reminder 4 Delay (Minutes)</label>
                        <input type="number" class="form-control rounded-3" name="reminder_4_delay" value="<?php echo htmlspecialchars($settings['reminder_4_delay'] ?? '4320'); ?>">
                        <small class="text-muted">Default: 4320 mins (3 days)</small>
                    </div>
                </div>

                <div class="alert alert-primary border-0 rounded-3 p-3 mb-4 d-flex align-items-center gap-2">
                    <i class="fas fa-info-circle fs-5"></i>
                    <div class="small">
                        <strong>Available Variables:</strong> <code>{CustomerName}</code>, <code>{ProductNames}</code>, <code>{CartTotal}</code>, <code>{RecoveryLink}</code>, <code>{CouponCode}</code>, <code>{CouponDiscount}</code>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <label class="form-label fw-bold text-dark">Reminder 1 Template (First Nudge)</label>
                            <textarea class="form-control mb-2" name="reminder_1_message" rows="3"><?php echo htmlspecialchars($settings['reminder_1_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_1" id="metaTpl1" value="<?php echo htmlspecialchars($settings['meta_template_1'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl1"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <label class="form-label fw-bold text-dark">Reminder 2 Template (Stock Warning)</label>
                            <textarea class="form-control mb-2" name="reminder_2_message" rows="3"><?php echo htmlspecialchars($settings['reminder_2_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_2" id="metaTpl2" value="<?php echo htmlspecialchars($settings['meta_template_2'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl2"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <label class="form-label fw-bold text-dark">Reminder 3 Template (Urgency)</label>
                            <textarea class="form-control mb-2" name="reminder_3_message" rows="3"><?php echo htmlspecialchars($settings['reminder_3_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_3" id="metaTpl3" value="<?php echo htmlspecialchars($settings['meta_template_3'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl3"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <label class="form-label fw-bold text-dark">Reminder 4 Template (Discount Coupon)</label>
                            <textarea class="form-control mb-2" name="reminder_4_message" rows="3"><?php echo htmlspecialchars($settings['reminder_4_message'] ?? ''); ?></textarea>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control" name="meta_template_4" id="metaTpl4" value="<?php echo htmlspecialchars($settings['meta_template_4'] ?? ''); ?>" placeholder="Template name...">
                                <button class="btn btn-outline-primary btn-sync-tpl" type="button" data-target="metaTpl4"><i class="fas fa-list"></i> Fetch</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Discount Coupon (%)</label>
                        <input type="number" step="0.1" class="form-control rounded-3" name="coupon_discount_percent" value="<?php echo htmlspecialchars($settings['coupon_discount_percent'] ?? '10'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Coupon Validity (Hours)</label>
                        <input type="number" class="form-control rounded-3" name="coupon_validity_hours" value="<?php echo htmlspecialchars($settings['coupon_validity_hours'] ?? '48'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Auto-Expire Old Carts (Days)</label>
                        <input type="number" class="form-control rounded-3" name="auto_expire_days" value="<?php echo htmlspecialchars($settings['auto_expire_days'] ?? '7'); ?>">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Meta Template Language Code</label>
                        <input type="text" class="form-control rounded-3" name="meta_template_lang" id="metaTplLang" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en'); ?>">
                        <small class="text-muted">Default: 'en' or 'hi'</small>
                        <div id="tplSyncStatus" class="small mt-2 d-none"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Cron Security Secret Key</label>
                        <input type="text" class="form-control rounded-3" name="cron_secret_key" id="cronSecretKey" value="<?php echo htmlspecialchars($settings['cron_secret_key'] ?? 'sagar_cart_recovery_cron_secret'); ?>">
                        <small class="text-muted">Used to authenticate URL cron requests.</small>
                    </div>
                </div>

                <!-- Hostinger Cron Guide -->
                <div class="card border-0 bg-light p-3 rounded-3 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-clock text-warning fs-5"></i>
                        <h6 class="fw-bold mb-0">Hostinger Cron Job Setup Guide</h6>
                    </div>
                    <p class="small text-muted mb-3">Copy either command into Hostinger cPanel -> Cron Jobs (Schedule: Every 5 minutes):</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option 1: PHP CLI Command (Recommended)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace bg-white" readonly id="cronCmdCli" value="/usr/bin/php /home/u902894566/domains/sagarstarters.com/public_html/cron/cart_abandonment.php">
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText($('#cronCmdCli').val()); alert('CLI Command Copied!');"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option 2: URL / Custom Cron Command</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace bg-white" readonly id="cronUrlHttp" value="curl -s -L &quot;https://www.sagarstarters.com/cron/cart_abandonment.php?key=<?php echo htmlspecialchars($settings['cron_secret_key'] ?? 'sagar_cart_recovery_cron_secret'); ?>&quot;">
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText($('#cronUrlHttp').val()); alert('URL Cron Command Copied!');"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="metaTemplatesList" class="mb-4 d-none p-3 border rounded-3 bg-white shadow-sm overflow-auto" style="max-height: 250px;">
                    <h6 class="fw-bold mb-2">Select Approved Meta Template</h6>
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

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4 py-2 rounded-3 shadow-sm" onclick="saveSettings()">
                        <i class="fas fa-save me-2"></i>Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  4. DATA FILTER BAR & TABLE                                -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <!-- Filter Tabs -->
        <div class="ac-filter-tabs">
            <button class="ac-filter-tab active" data-status="all" onclick="setFilterStatus('all', this)">
                <i class="fas fa-list-ul"></i> All Carts
            </button>
            <button class="ac-filter-tab" data-status="active" onclick="setFilterStatus('active', this)">
                <i class="fas fa-bolt text-warning"></i> Active
            </button>
            <button class="ac-filter-tab" data-status="converted" onclick="setFilterStatus('converted', this)">
                <i class="fas fa-check-circle text-success"></i> Recovered
            </button>
            <button class="ac-filter-tab" data-status="expired" onclick="setFilterStatus('expired', this)">
                <i class="fas fa-clock text-secondary"></i> Expired
            </button>
        </div>

        <!-- Search & Refresh Bar -->
        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0" style="max-width: 420px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted rounded-start-3"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0 rounded-end-3" id="searchKeyword" placeholder="Search customer name or phone..." onkeypress="if(event.key==='Enter') loadCarts(1)">
            </div>
            <button type="button" class="btn ac-btn-refresh shadow-sm" onclick="refreshTableAndStats()" title="Refresh list (Auto-refreshes every 60s)">
                <i class="fas fa-sync-alt" id="refreshSpinIcon"></i>
                <span class="d-none d-sm-inline">Refresh</span>
            </button>
        </div>
    </div>

    <!-- Table Card -->
    <div class="ac-table-container">
        <div class="table-responsive">
            <table class="table ac-table align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Customer</th>
                        <th>Cart Items</th>
                        <th>Cart Total</th>
                        <th>Abandoned Time</th>
                        <th>Reminder Stage</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Quick Actions</th>
                    </tr>
                </thead>
                <tbody id="cartsTableBody">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-light border-top d-flex align-items-center justify-content-between" id="paginationContainer">
            <!-- Pagination Controls -->
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  5. CART INSPECTION MODAL                                   -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="cartDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-4 border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="ac-avatar-circle" id="modalCustomerAvatar">?</div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="modalCustomerName">Customer Details</h5>
                        <div class="small text-white-50" id="modalCustomerPhone">Phone</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-white border rounded-3">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Cart Total Value</div>
                            <div class="fs-4 fw-bold text-success" id="modalCartTotal">₹0.00</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-white border rounded-3">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Recovery Token & Link</div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control bg-light" readonly id="modalRecoveryLink" value="">
                                <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText($('#modalRecoveryLink').val()); alert('Link copied!');"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Cart Contents</h6>
                <div class="bg-white border rounded-3 p-3 mb-4" id="modalCartItems">
                    <!-- Cart items list -->
                </div>

                <h6 class="fw-bold mb-2">WhatsApp Log History</h6>
                <div class="bg-white border rounded-3 p-3" id="modalCartLogs">
                    <!-- Logs list -->
                </div>
            </div>
            <div class="modal-footer bg-white border-top p-3 justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm rounded-3" onclick="resetModalReminders()">
                        <i class="fas fa-undo me-1"></i> Reset Stages to 0
                    </button>
                    <button type="button" class="btn btn-success btn-sm rounded-3" onclick="sendModalReminder(1)">
                        <i class="fab fa-whatsapp me-1"></i> Send Stage 1
                    </button>
                </div>
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'all';
let currentPage = 1;
const globalCurrency = '<?php echo $currency; ?>';

function parseSqlDate(dateStr) {
    if (!dateStr) return new Date();
    const p = dateStr.split(/[- :]/);
    if (p.length >= 6) {
        return new Date(p[0], p[1] - 1, p[2], p[3], p[4], p[5]);
    }
    return new Date(dateStr.replace(/-/g, '/'));
}

function timeAgo(dateStr) {
    if (!dateStr) return 'Recently';
    const now = new Date();
    const date = parseSqlDate(dateStr);
    const seconds = Math.floor((now - date) / 1000);
    if (isNaN(seconds) || seconds < 0 || seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

function setFilterStatus(status, el) {
    currentStatusFilter = status;
    $('.ac-filter-tab').removeClass('active');
    $(el).addClass('active');
    loadCarts(1);
}

function loadCarts(page = 1) {
    currentPage = page;
    const search = $('#searchKeyword').val();

    $('#cartsTableBody').html(`
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-muted small mt-2">Loading abandoned carts...</div>
            </td>
        </tr>
    `);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        data: { action: 'get_carts', status: currentStatusFilter, search: search, page: page },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.carts) {
                renderCartsTable(response.data.carts.data);
                renderPagination(response.data.carts.total_pages, page, response.data.carts.total);
            } else {
                renderEmptyTable();
            }
        },
        error: function() {
            $('#cartsTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i> Failed to load data. Please refresh.
                    </td>
                </tr>
            `);
            $('#paginationContainer').empty();
        }
    });
}

function renderEmptyTable() {
    $('#cartsTableBody').html(`
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="p-4">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3 opacity-25"></i>
                    <h6 class="fw-bold text-muted">No Abandoned Carts Found</h6>
                    <p class="small text-muted mb-0">No records match your selected status or search filter.</p>
                </div>
            </td>
        </tr>
    `);
    $('#paginationContainer').empty();
}

function renderCartsTable(carts) {
    if (!carts || carts.length === 0) {
        renderEmptyTable();
        return;
    }

    let html = '';
    carts.forEach(cart => {
        const customerName = cart.customer_name || 'Guest User';
        const customerPhone = cart.customer_phone || 'No Phone';
        const initials = customerName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        let productText = cart.product_names || 'Cart Items';
        if (productText.length > 55) productText = productText.substring(0, 52) + '...';

        const cartTotal = parseFloat(cart.cart_total || 0).toFixed(2);
        const timeDisplay = timeAgo(cart.updated_at || cart.created_at);

        // Timeline dots
        const step = parseInt(cart.reminder_step || 0);
        let timelineHtml = '<div class="ac-reminder-timeline">';
        for (let i = 1; i <= 4; i++) {
            let cls = 'pending';
            if (i < step) cls = 'done';
            else if (i === step) cls = 'done';
            timelineHtml += `<div class="ac-reminder-step ${cls}" title="Reminder ${i}">${i}</div>`;
        }
        timelineHtml += `<span class="small text-muted fw-bold ms-1">${step}/4</span></div>`;

        // Status pill
        let statusHtml = '';
        if (cart.status === 'active') {
            statusHtml = '<span class="ac-status-pill ac-status-active"><i class="fas fa-dot-circle"></i> Active</span>';
        } else if (cart.status === 'converted') {
            statusHtml = '<span class="ac-status-pill ac-status-converted"><i class="fas fa-check-circle"></i> Recovered</span>';
        } else {
            statusHtml = '<span class="ac-status-pill ac-status-expired"><i class="fas fa-clock"></i> Expired</span>';
        }

        // WhatsApp direct link
        let cleanPhone = customerPhone.replace(/[^0-9]/g, '');
        if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;

        const cartJsonEscaped = htmlEscape(JSON.stringify(cart));

        html += `
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="ac-avatar-circle">${initials}</div>
                        <div>
                            <div class="fw-bold text-dark">${customerName}</div>
                            <div class="small text-muted d-flex align-items-center gap-1">
                                <i class="fas fa-phone-alt fs-7"></i>
                                ${customerPhone !== 'No Phone' ? `<a href="https://wa.me/${cleanPhone}" target="_blank" class="text-decoration-none text-muted">${customerPhone}</a>` : customerPhone}
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark mb-1" title="${htmlEscape(cart.product_names || '')}">${productText}</div>
                    <span class="badge bg-light text-dark border">Cart ID #${cart.id}</span>
                </td>
                <td>
                    <div class="fw-bold text-dark fs-6">${globalCurrency}${cartTotal}</div>
                </td>
                <td>
                    <div class="small text-dark fw-semibold"><i class="far fa-clock text-warning me-1"></i>${timeDisplay}</div>
                </td>
                <td>${timelineHtml}</td>
                <td>${statusHtml}</td>
                <td class="pe-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                        <button type="button" class="ac-btn-icon ac-btn-wa" onclick="sendReminder(${cart.id}, this, 0)" title="Send Next Reminder">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button type="button" class="ac-btn-icon ac-btn-info" onclick='openCartModal(${cartJsonEscaped})' title="Inspect Cart & Logs">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="ac-btn-icon ac-btn-warn" onclick="resetReminders(${cart.id}, this)" title="Reset Reminder Stages (0/4)">
                            <i class="fas fa-undo"></i>
                        </button>
                        ${cart.status === 'active' ? `
                            <button type="button" class="ac-btn-icon ac-btn-del" onclick="markExpired(${cart.id}, this)" title="Mark Expired">
                                <i class="fas fa-ban"></i>
                            </button>
                        ` : ''}
                        <button type="button" class="ac-btn-icon ac-btn-del" onclick="deleteCart(${cart.id}, this)" title="Delete Cart">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    $('#cartsTableBody').html(html);
}

function htmlEscape(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function renderPagination(totalPages, page, totalRecords) {
    if (!totalPages || totalPages <= 1) {
        $('#paginationContainer').html(`<div class="small text-muted">Total records: <strong>${totalRecords}</strong></div>`);
        return;
    }

    let html = `<div class="small text-muted">Showing Page <strong>${page}</strong> of <strong>${totalPages}</strong> (${totalRecords} total)</div>`;
    html += '<nav><ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${page - 1})">Prev</a></li>`;

    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${i})">${i}</a></li>`;
    }

    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadCarts(${page + 1})">Next</a></li>`;
    html += '</ul></nav>';

    $('#paginationContainer').html(html);
}

let currentModalCartId = 0;

function openCartModal(cart) {
    currentModalCartId = cart.id;
    const customerName = cart.customer_name || 'Guest User';
    const customerPhone = cart.customer_phone || 'No Phone';
    const initials = customerName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

    $('#modalCustomerAvatar').text(initials);
    $('#modalCustomerName').text(customerName);
    $('#modalCustomerPhone').text(customerPhone + ' | Cart ID #' + cart.id);
    $('#modalCartTotal').text(globalCurrency + parseFloat(cart.cart_total || 0).toFixed(2));

    const rawSiteUrl = '<?php echo rtrim(defined('SITE_URL') ? SITE_URL : '', '/'); ?>';
    const siteUrl = rawSiteUrl.replace(/\/(admin|api|user|auth|cron)(\/.*)?$/i, '');
    const link = siteUrl + '/recover_cart.php?token=' + encodeURIComponent(cart.recovery_token || '');
    $('#modalRecoveryLink').val(link);

    // Render items list
    let itemsHtml = `<div class="fw-bold text-dark mb-2">${htmlEscape(cart.product_names || 'Cart Items')}</div>`;
    $('#modalCartItems').html(itemsHtml);

    loadModalLogs(cart.id);

    const modal = new mdb.Modal(document.getElementById('cartDetailModal'));
    modal.show();
}

function loadModalLogs(cartId) {
    $('#modalCartLogs').html('<div class="small text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Loading WhatsApp logs...</div>');

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        data: { action: 'get_cart_logs', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let logsHtml = '<ul class="list-group list-group-flush small">';
                res.data.forEach(log => {
                    const statusText = log.status || '';
                    let badgeClass = 'bg-secondary';
                    if (statusText.toLowerCase().includes('sent via meta')) badgeClass = 'bg-success';
                    else if (statusText.toLowerCase().includes('failed') || statusText.toLowerCase().includes('error')) badgeClass = 'bg-danger';
                    else if (statusText.toLowerCase().includes('wa.me') || statusText.toLowerCase().includes('reset')) badgeClass = 'bg-warning text-dark';

                    logsHtml += `
                        <li class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <span class="badge ${badgeClass} me-1">${htmlEscape(log.sending_mode || 'api')}</span>
                                <strong class="text-dark">[${log.created_at}]</strong>
                                <div class="text-muted mt-1" style="word-break: break-word;">${htmlEscape(statusText)}</div>
                            </div>
                        </li>
                    `;
                });
                logsHtml += '</ul>';
                $('#modalCartLogs').html(logsHtml);
            } else {
                $('#modalCartLogs').html('<div class="small text-muted">No WhatsApp log entries for this cart yet.</div>');
            }
        },
        error: function() {
            $('#modalCartLogs').html('<div class="small text-danger">Failed to fetch logs.</div>');
        }
    });
}

function resetModalReminders() {
    if (currentModalCartId > 0) {
        resetReminders(currentModalCartId, null, function() {
            loadModalLogs(currentModalCartId);
        });
    }
}

function sendModalReminder(level = 1) {
    if (currentModalCartId > 0) {
        sendReminder(currentModalCartId, null, level, function() {
            loadModalLogs(currentModalCartId);
        });
    }
}

function resetReminders(cartId, btn, callback = null) {
    if (!confirm(`Reset all reminder stages for Cart #${cartId} back to 0?`)) return;

    const $btn = btn ? $(btn) : null;
    const originalHtml = $btn ? $btn.html() : '';
    if ($btn) $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'reset_reminders', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            if (res.success) {
                alert(res.message || 'Reminder stages reset to 0!');
                refreshTableAndStats();
                if (callback) callback();
            } else {
                alert(res.error || res.message || 'Error resetting reminders');
            }
        },
        error: function() {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            alert('Request failed');
        }
    });
}

function sendReminder(cartId, btn, level = 0, callback = null) {
    const $btn = btn ? $(btn) : null;
    const originalHtml = $btn ? $btn.html() : '';
    if ($btn) $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'send_reminder', cart_id: cartId, level: level },
        dataType: 'json',
        success: function(res) {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            if (res.success) {
                if (res.link) {
                    window.open(res.link, '_blank');
                    alert('WhatsApp Web opened in new tab.');
                } else {
                    alert(res.message || 'Reminder sent successfully!');
                }
                refreshTableAndStats();
                if (callback) callback();
            } else {
                alert(res.error || res.message || 'Error sending reminder');
            }
        },
        error: function() {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            alert('Request failed');
        }
    });
}

function markExpired(cartId, btn) {
    if (!confirm('Mark this cart as expired? No further automated reminders will be sent.')) return;

    const $btn = $(btn);
    const originalHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'mark_expired', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
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
    if (!confirm('Are you sure you want to delete this cart record?')) return;

    const $btn = $(btn);
    const originalHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'delete_cart', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
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
    const formData = new FormData(document.getElementById('settingsForm'));
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
            if (res.success) {
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
            if (res.success && res.data) {
                $('#stat_active_carts').text(res.data.active_carts || 0);
                $('#stat_recovery_rate').text((res.data.recovery_rate || 0) + '%');
                $('#stat_lost_value').text(globalCurrency + parseFloat(res.data.total_lost_value || 0).toFixed(2));
                $('#stat_pending').text(res.data.pending_reminders || 0);
            }
        }
    });
}

function refreshTableAndStats() {
    const icon = document.getElementById('refreshSpinIcon');
    if (icon) icon.classList.add('fa-spin');

    loadCarts(currentPage);
    refreshStats();

    setTimeout(function() {
        if (icon) icon.classList.remove('fa-spin');
    }, 1000);
}

$(document).ready(function() {
    loadCarts(1);

    // Auto-refresh table data & stats every 60 seconds (1 minute)
    setInterval(function() {
        refreshTableAndStats();
    }, 60000);
});

// Meta Templates Sync
let currentTplTarget = null;
document.addEventListener('DOMContentLoaded', function() {
    const btnSyncs = document.querySelectorAll('.btn-sync-tpl');
    const tplStatus = document.getElementById('tplSyncStatus');
    const tplList = document.getElementById('metaTemplatesList');
    const tplTableBody = document.getElementById('tplTableBody');

    btnSyncs.forEach(btn => {
        btn.addEventListener('click', function() {
            currentTplTarget = this.getAttribute('data-target');

            btnSyncs.forEach(b => { b.disabled = true; });
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            tplStatus.className = 'small mt-2 fw-bold text-info';
            tplStatus.innerText = 'Connecting to Meta API...';
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
                        tplStatus.innerText = 'Templates fetched successfully!';

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
