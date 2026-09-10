<?php
include 'admin_header.php';
require_once '../includes/AbandonedCartService.php';

$abandonedCartService = new AbandonedCartService($conn);
$dashboardData = $abandonedCartService->getAdminDashboardData();
$stats = $dashboardData['stats'];
$settings = $dashboardData['settings'];
$waInfo = $dashboardData['whatsapp_info'] ?? ['mode' => 'web', 'is_enabled' => true, 'has_api_creds' => false];
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

    <?php
    // ── Diagnostic Banner: Show prominent warning if setup is incomplete ──
    $missingTemplates = $waInfo['mode'] === 'api' && (
        empty($settings['meta_template_1']) &&
        empty($settings['meta_template_2']) &&
        empty($settings['meta_template_3']) &&
        empty($settings['meta_template_4'])
    );
    $noApiCreds = $waInfo['mode'] === 'api' && !$waInfo['has_api_creds'];
    ?>

    <?php if ($noApiCreds): ?>
    <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center gap-3 shadow-sm" style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border-left: 4px solid #ef4444 !important;">
        <i class="fas fa-exclamation-triangle fs-4 text-danger flex-shrink-0"></i>
        <div>
            <div class="fw-bold text-danger mb-1">⚠️ Meta Cloud API Credentials Missing!</div>
            <div class="small text-dark">WhatsApp is set to <strong>API Mode</strong> but the <strong>API Token</strong> or <strong>Phone Number ID</strong> is not configured. All cart reminders will fail until this is fixed.</div>
            <a href="manage_whatsapp_settings.php" class="btn btn-sm btn-danger mt-2 rounded-pill px-3">
                <i class="fas fa-cog me-1"></i> Fix WhatsApp Credentials
            </a>
        </div>
    </div>
    <?php elseif ($missingTemplates && $waInfo['is_enabled']): ?>
    <div class="alert alert-warning border-0 rounded-3 mb-4 d-flex align-items-center gap-3 shadow-sm" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-left: 4px solid #f59e0b !important;">
        <i class="fas fa-exclamation-circle fs-4 text-warning flex-shrink-0"></i>
        <div>
            <div class="fw-bold text-dark mb-1">⚠️ Meta WhatsApp Templates Not Configured for Cart Reminders!</div>
            <div class="small text-dark mb-2">
                WhatsApp is in <strong>Meta Cloud API Mode</strong> but no WhatsApp Template Names are selected for Reminders 1–4. <strong>Meta Cloud API strictly requires approved WhatsApp Templates</strong> to deliver messages outside the 24-hour service window — free-text messages will be silently dropped by Meta.
            </div>
            <div class="small text-muted">
                <strong>Action Required:</strong> Click <strong>"Settings &amp; Templates"</strong> → scroll to the Reminder Templates section → click <strong>"Fetch / Select"</strong> next to each Reminder to pick your approved Meta Template.
            </div>
            <button class="btn btn-sm btn-warning mt-2 rounded-pill px-3 fw-bold" type="button" data-mdb-toggle="collapse" data-mdb-target="#settingsCollapse">
                <i class="fas fa-sliders-h me-1"></i> Open Settings &amp; Configure Templates
            </button>
        </div>
    </div>
    <?php elseif ($waInfo['mode'] === 'web'): ?>
    <div class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-center gap-3 shadow-sm" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-left: 4px solid #3b82f6 !important;">
        <i class="fas fa-info-circle fs-4 text-primary flex-shrink-0"></i>
        <div>
            <div class="fw-bold text-primary mb-1">WhatsApp Web Mode — Manual Sending Required</div>
            <div class="small text-dark">Currently in <strong>WhatsApp Web mode</strong>. Automated background reminders <strong>require Meta Cloud API mode</strong>. In web mode, clicking the WhatsApp button opens a pre-filled WhatsApp Web link for manual sending. <a href="manage_whatsapp_settings.php" class="fw-bold">Switch to API Mode →</a></div>
        </div>
    </div>
    <?php endif; ?>

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
                <input type="hidden" name="meta_template_lang" id="metaTplLang" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en'); ?>">

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

                <!-- PROMINENT QUICK SELECT PRESET BAR -->
                <div class="card border-0 p-3 rounded-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-left: 5px solid #10b981 !important;">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                        <div>
                            <div class="fw-bold text-success fs-6 mb-1">
                                <i class="fas fa-magic me-2"></i> Quick Select Meta Templates (1-Click Auto-Fill)
                            </div>
                            <div class="small text-dark">
                                Niche diye gaye button par click karke sabhi stages me approved Meta Cart Reminder Templates turant set karein.
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-sm" onclick="applyOfficialCartTemplates()" title="Auto-fill Stage 1, 2, 3, and 4 with official Meta approved reminder templates">
                                <i class="fas fa-star me-1"></i> Apply 4 Official Cart Templates (Stages 1-4)
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Reminder 1 (First Nudge)</label>
                                <span class="badge bg-warning text-dark border small" title="Used when sending manually via WhatsApp Web"><i class="fab fa-whatsapp me-1"></i> WhatsApp Web Text</span>
                            </div>
                            <textarea class="form-control mb-2" name="reminder_1_message" rows="3"><?php echo htmlspecialchars($settings['reminder_1_message'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-primary"><i class="fas fa-robot me-1"></i> Meta Cloud API Template:</span>
                                <span class="badge bg-light text-muted border small" style="font-size: 0.72rem;">Meta Approved Name</span>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control font-monospace" name="meta_template_1" id="metaTpl1" value="<?php echo htmlspecialchars($settings['meta_template_1'] ?? 'reminder_1_gentle_nudge'); ?>" placeholder="reminder_1_gentle_nudge">
                                <button class="btn btn-outline-primary" type="button" onclick="openMetaTemplatePicker('metaTpl1')"><i class="fas fa-list me-1"></i> Fetch / Select</button>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="text-muted small fw-semibold"><i class="fas fa-magic text-primary me-1"></i> Quick:</span>
                                <button type="button" class="btn btn-xs btn-primary py-0 px-2 rounded-pill fw-bold" onclick="setTplInput('metaTpl1', 'reminder_1_gentle_nudge')">reminder_1_gentle_nudge</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Reminder 2 (Stock Warning)</label>
                                <span class="badge bg-warning text-dark border small" title="Used when sending manually via WhatsApp Web"><i class="fab fa-whatsapp me-1"></i> WhatsApp Web Text</span>
                            </div>
                            <textarea class="form-control mb-2" name="reminder_2_message" rows="3"><?php echo htmlspecialchars($settings['reminder_2_message'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-primary"><i class="fas fa-robot me-1"></i> Meta Cloud API Template:</span>
                                <span class="badge bg-light text-muted border small" style="font-size: 0.72rem;">Meta Approved Name</span>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control font-monospace" name="meta_template_2" id="metaTpl2" value="<?php echo htmlspecialchars($settings['meta_template_2'] ?? 'reminder_2_follow_up'); ?>" placeholder="reminder_2_follow_up">
                                <button class="btn btn-outline-primary" type="button" onclick="openMetaTemplatePicker('metaTpl2')"><i class="fas fa-list me-1"></i> Fetch / Select</button>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="text-muted small fw-semibold"><i class="fas fa-magic text-primary me-1"></i> Quick:</span>
                                <button type="button" class="btn btn-xs btn-primary py-0 px-2 rounded-pill fw-bold" onclick="setTplInput('metaTpl2', 'reminder_2_follow_up')">reminder_2_follow_up</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Reminder 3 (Urgency)</label>
                                <span class="badge bg-warning text-dark border small" title="Used when sending manually via WhatsApp Web"><i class="fab fa-whatsapp me-1"></i> WhatsApp Web Text</span>
                            </div>
                            <textarea class="form-control mb-2" name="reminder_3_message" rows="3"><?php echo htmlspecialchars($settings['reminder_3_message'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-primary"><i class="fas fa-robot me-1"></i> Meta Cloud API Template:</span>
                                <span class="badge bg-light text-muted border small" style="font-size: 0.72rem;">Meta Approved Name</span>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control font-monospace" name="meta_template_3" id="metaTpl3" value="<?php echo htmlspecialchars($settings['meta_template_3'] ?? 'reminder_3_urgency'); ?>" placeholder="reminder_3_urgency">
                                <button class="btn btn-outline-primary" type="button" onclick="openMetaTemplatePicker('metaTpl3')"><i class="fas fa-list me-1"></i> Fetch / Select</button>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="text-muted small fw-semibold"><i class="fas fa-magic text-primary me-1"></i> Quick:</span>
                                <button type="button" class="btn btn-xs btn-primary py-0 px-2 rounded-pill fw-bold" onclick="setTplInput('metaTpl3', 'reminder_3_urgency')">reminder_3_urgency</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Reminder 4 (Discount Coupon)</label>
                                <span class="badge bg-warning text-dark border small" title="Used when sending manually via WhatsApp Web"><i class="fab fa-whatsapp me-1"></i> WhatsApp Web Text</span>
                            </div>
                            <textarea class="form-control mb-2" name="reminder_4_message" rows="3"><?php echo htmlspecialchars($settings['reminder_4_message'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-primary"><i class="fas fa-robot me-1"></i> Meta Cloud API Template:</span>
                                <span class="badge bg-light text-muted border small" style="font-size: 0.72rem;">Meta Approved Name</span>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light fw-semibold">Meta Template</span>
                                <input type="text" class="form-control font-monospace" name="meta_template_4" id="metaTpl4" value="<?php echo htmlspecialchars($settings['meta_template_4'] ?? 'reminder_4_coupon_discount'); ?>" placeholder="reminder_4_coupon_discount">
                                <button class="btn btn-outline-primary" type="button" onclick="openMetaTemplatePicker('metaTpl4')"><i class="fas fa-list me-1"></i> Fetch / Select</button>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="text-muted small fw-semibold"><i class="fas fa-magic text-primary me-1"></i> Quick:</span>
                                <button type="button" class="btn btn-xs btn-primary py-0 px-2 rounded-pill fw-bold" onclick="setTplInput('metaTpl4', 'reminder_4_coupon_discou')">reminder_4_coupon_discou</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill fw-bold" onclick="setTplInput('metaTpl4', 'reminder_4_coupon_discount')">reminder_4_coupon_discount</button>
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
                        <div class="input-group input-group-sm mb-1">
                            <input type="text" class="form-control font-monospace" name="meta_template_lang" id="metaTplLang" value="<?php echo htmlspecialchars($settings['meta_template_lang'] ?? 'en_US'); ?>" placeholder="en_US">
                            <button type="button" class="btn btn-outline-primary fw-bold" onclick="document.getElementById('metaTplLang').value='en_US';">en_US (Recommended)</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('metaTplLang').value='en';">en</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('metaTplLang').value='hi';">hi</button>
                        </div>
                        <small class="text-muted">Standard Meta English is <code>en_US</code>. Meta throws <em>#132001</em> if requested language doesn't match approval locale. System auto-retries <code>en_US</code>, <code>en</code>, <code>en_GB</code>.</small>
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
                                <input type="text" class="form-control font-monospace bg-white" readonly id="cronUrlHttp" value="curl -s -L -A &quot;Mozilla/5.0&quot; &quot;https://www.sagarstarters.com/cron/cart_abandonment.php?key=<?php echo htmlspecialchars($settings['cron_secret_key'] ?? 'sagar_cart_recovery_cron_secret'); ?>&quot;">
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText($('#cronUrlHttp').val()); alert('URL Cron Command Copied!');"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
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
    <!--  WHATSAPP SENDING MODE STATUS BANNER                        -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="mb-4">
        <?php if (($waInfo['mode'] ?? 'web') === 'api'): ?>
            <div class="alert alert-primary border-0 rounded-4 p-3 shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark d-flex align-items-center gap-2 flex-wrap">
                            WhatsApp Mode: <span class="badge bg-primary">Meta Cloud API (Automated 24/7 Delivery)</span>
                            <?php if (!$waInfo['has_api_creds']): ?>
                                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> API Credentials Incomplete</span>
                            <?php elseif (empty($settings['meta_template_1'])): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i> Meta Template Not Configured</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Template: <?php echo htmlspecialchars($settings['meta_template_1']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php if (empty($settings['meta_template_1'])): ?>
                                <span class="text-danger fw-semibold"><i class="fas fa-info-circle me-1"></i> Meta Cloud API Notice:</span> Customer ko message pahuchane ke liye Meta Business Manager ka approved Template zaroori hai (outside 24h window). Kripya <strong>Settings & Templates</strong> me template select karein ya WhatsApp icon click karke <strong>WhatsApp Web</strong> se turant bhejein.
                            <?php else: ?>
                                Customer ko automated WhatsApp messages Meta Cloud API template se direct bheje jate hain.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-sm btn-primary fw-bold rounded-pill shadow-sm text-nowrap" type="button" onclick="applyOfficialCartTemplates()" title="1-Click Set All Stages to Official Cart Reminder Templates">
                        <i class="fas fa-magic me-1"></i> Set Cart Templates (1-4)
                    </button>
                    <button class="btn btn-sm btn-outline-primary rounded-3 text-nowrap" type="button" data-mdb-toggle="collapse" data-mdb-target="#settingsCollapse"><i class="fas fa-sliders-h me-1"></i> Cart Templates</button>
                    <a href="manage_whatsapp_settings.php" class="btn btn-sm btn-outline-secondary rounded-3 text-nowrap"><i class="fas fa-cog me-1"></i> API Settings</a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert border-0 rounded-4 p-3 shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" style="background-color: #fffbeb; border: 1px solid #fde68a !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.35rem; background-color: #25d366;">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                            WhatsApp Mode: <span class="badge bg-warning text-dark border">WhatsApp Web (Manual Redirect)</span>
                        </div>
                        <div class="small text-dark mt-1">
                            Abhi WhatsApp <strong>Web Mode</strong> par hai. WhatsApp icon click karne par WhatsApp Web chat window khulega jahan aapko <strong>Send</strong> button dabana hoga. (Bina WhatsApp Web khole 24/7 automated direct messages ke liye WhatsApp Settings me <strong>Meta API Mode</strong> configure karein).
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="manage_whatsapp_settings.php" class="btn btn-sm btn-outline-dark rounded-3 text-nowrap"><i class="fas fa-cog me-1"></i> WhatsApp Settings</a>
                </div>
            </div>
        <?php endif; ?>
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

                <!-- Reminder Stages & WhatsApp Dispatch -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fab fa-whatsapp text-success me-1"></i> Reminder Stages & WhatsApp Dispatch</h6>
                    <span class="small text-muted" id="modalWaModeTag"></span>
                </div>
                <div class="bg-white border rounded-3 p-3 mb-4" id="modalCartStages">
                    <div class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i> Loading stages & messages...</div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history me-1"></i> WhatsApp Log History</h6>
                    <button type="button" class="btn btn-outline-info btn-xs py-1 px-2 rounded-pill" onclick="toggleRawApiLog()">
                        <i class="fas fa-terminal me-1"></i> Live Meta API Raw Log
                    </button>
                </div>
                <div id="modalRawApiLogContainer" class="d-none mb-3">
                    <div class="d-flex align-items-center justify-content-between bg-dark text-white px-3 py-1 rounded-top" style="font-size:11px;">
                        <span><i class="fas fa-satellite-dish text-info me-1"></i> Live Meta Graph API Delivery Log (Recent)</span>
                        <button type="button" class="btn-close btn-close-white" style="transform: scale(0.7);" onclick="$('#modalRawApiLogContainer').addClass('d-none')"></button>
                    </div>
                    <div class="bg-black text-light p-3 rounded-bottom border border-dark" style="font-family: monospace; font-size: 11px; max-height: 220px; overflow-y: auto; white-space: pre-wrap;" id="modalRawApiLogContent">
                        <i class="fas fa-spinner fa-spin me-1"></i> Loading live API log...
                    </div>
                </div>
                <div class="bg-white border rounded-3 p-3" id="modalCartLogs">
                    <!-- Logs list -->
                </div>
            </div>
            <div class="modal-footer bg-white border-top p-3 justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm rounded-3" onclick="resetModalReminders()">
                        <i class="fas fa-undo me-1"></i> Reset All Stages to 0
                    </button>
                </div>
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== UNIVERSAL META TEMPLATE PICKER MODAL ===== -->
<div class="modal fade" id="metaTemplatePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-4">
                <div>
                    <h5 class="modal-title fw-bold text-primary"><i class="fab fa-whatsapp me-2"></i>Select Meta Approved Template</h5>
                    <small class="text-muted">Live sync from your WhatsApp Business Account (WABA).</small>
                </div>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <input type="text" id="tplSearchFilter" class="form-control form-control-sm flex-grow-1 bg-light" placeholder="🔍 Search template by name..." oninput="filterTemplateRows(this.value)">
                    <button type="button" id="btnRefreshModalTpl" class="btn btn-sm btn-outline-primary fw-bold rounded-pill text-nowrap" onclick="fetchMetaTemplates()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh from Meta
                    </button>
                </div>

                <div id="modalTplStatus" class="alert alert-info py-2 small d-none"></div>

                <!-- Quick Selection Chips -->
                <div class="p-2 mb-3 bg-light rounded-3 d-flex align-items-center gap-2 flex-wrap small">
                    <span class="fw-bold text-muted"><i class="fas fa-magic me-1"></i> Quick Select:</span>
                    <button type="button" class="btn btn-sm btn-primary py-0 px-2 rounded-pill fw-bold" onclick="applyOfficialCartTemplates()" title="Auto-fill Stage 1, 2, 3, and 4 with official Meta approved reminder templates">
                        <i class="fas fa-star me-1"></i> Apply 4 Official Cart Templates (Stages 1-4)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill fw-bold" onclick="selectTemplate('reminder_1_gentle_nudge', 'en')">
                        reminder_1_gentle_nudge
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill fw-bold" onclick="selectTemplate('reminder_2_follow_up', 'en')">
                        reminder_2_follow_up
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill fw-bold" onclick="selectTemplate('reminder_3_urgency', 'en')">
                        reminder_3_urgency
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill fw-bold" onclick="selectTemplate('reminder_4_coupon_discou', 'en')">
                        reminder_4_coupon_discou
                    </button>
                </div>

                <div class="table-responsive border rounded-3 bg-white" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Template Name & Preview</th>
                                <th>Lang</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modalTplTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Loading templates from Meta...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-3 justify-content-between">
                <div class="small text-muted">
                    <i class="fas fa-info-circle me-1"></i> Templates must be created and approved in Meta WhatsApp Business Manager.
                </div>
                <button type="button" class="btn btn-secondary btn-sm px-3 rounded-pill" data-mdb-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'all';
let currentPage = 1;
const globalCurrency = '<?php echo $currency; ?>';
const globalWaMode = '<?php echo addslashes($waInfo['mode'] ?? 'web'); ?>';
const globalWaHasCreds = <?php echo (!empty($waInfo['has_api_creds']) ? 'true' : 'false'); ?>;

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
        cache: false,
        data: { action: 'get_carts', status: currentStatusFilter, search: search, page: page, _ts: Date.now() },
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

let currentLoadedCarts = {};

function renderCartsTable(carts) {
    if (!carts || carts.length === 0) {
        renderEmptyTable();
        return;
    }

    currentLoadedCarts = {};
    carts.forEach(cart => {
        currentLoadedCarts[cart.id] = cart;
    });

    let html = '';
    carts.forEach(cart => {
        const cartId = parseInt(cart.id);
        const customerName = cart.customer_name || 'Guest User';
        const customerPhone = cart.customer_phone || 'No Phone';
        const initials = customerName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        let productText = cart.product_names || 'Cart Items';
        if (productText.length > 55) productText = productText.substring(0, 52) + '...';

        const cartTotal = parseFloat(cart.cart_total || 0).toFixed(2);
        const timeDisplay = timeAgo(cart.updated_at || cart.created_at);

        // Timeline dots (1 to 4)
        const step = parseInt(cart.reminder_step || 0);
        let timelineHtml = '<div class="ac-reminder-timeline">';
        for (let i = 1; i <= 4; i++) {
            const isSent = Boolean(cart['reminder_' + i + '_sent']);
            let cls = isSent ? 'done' : 'pending';
            let title = isSent ? `Reminder ${i}: Sent on ${cart['reminder_' + i + '_sent']}` : `Reminder ${i}: Not Sent`;
            timelineHtml += `<div class="ac-reminder-step ${cls}" title="${htmlEscape(title)}" style="cursor:pointer;" onclick="openCartModalById(${cartId})">${i}</div>`;
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

        html += `
            <tr id="cart-row-${cartId}" data-cart-id="${cartId}">
                <td class="ps-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="ac-avatar-circle">${initials}</div>
                        <div>
                            <div class="fw-bold text-dark">${htmlEscape(customerName)}</div>
                            <div class="small text-muted d-flex align-items-center gap-1">
                                <i class="fas fa-phone-alt fs-7"></i>
                                ${customerPhone !== 'No Phone' ? `<a href="https://wa.me/${cleanPhone}" target="_blank" class="text-decoration-none text-muted">${htmlEscape(customerPhone)}</a>` : customerPhone}
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark mb-1" title="${htmlEscape(cart.product_names || '')}">${htmlEscape(productText)}</div>
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
                        <button type="button" class="ac-btn-icon ac-btn-wa" onclick="handleRowWhatsAppClick(${cart.id}, this, '${htmlEscape(customerName)}', '${cleanPhone}')" title="${globalWaMode === 'web' ? 'Send via WhatsApp Web' : 'Send via Meta Cloud API'}">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        ${globalWaMode === 'api' ? `
                            <button type="button" class="ac-btn-icon border text-success" onclick="handleRowWhatsAppWebClick(${cart.id}, this, '${htmlEscape(customerName)}', '${cleanPhone}')" title="Send via WhatsApp Web (Manual Link)">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        ` : ''}
                        <button type="button" class="ac-btn-icon ac-btn-info" onclick="openCartModalById(${cart.id})" title="Inspect Cart & Stages">
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

function openCartModalById(cartId) {
    const cart = currentLoadedCarts[cartId];
    if (cart) {
        openCartModal(cart);
    }
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
let currentModalCartData = null;

function openCartModal(cart) {
    currentModalCartId = cart.id;
    currentModalCartData = cart;
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

    // Render stages with actions and previews
    loadModalStages(cart.id);

    // Load WhatsApp logs
    loadModalLogs(cart.id);

    const modal = new mdb.Modal(document.getElementById('cartDetailModal'));
    modal.show();
}

function loadModalStages(cartId) {
    $('#modalCartStages').html('<div class="small text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Loading stages & messages...</div>');
    $('#modalWaModeTag').html(globalWaMode === 'api' ? '<span class="badge bg-primary">Meta Cloud API</span>' : '<span class="badge bg-warning text-dark border">WhatsApp Web</span>');

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        data: { action: 'get_cart_preview', cart_id: cartId },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data && res.data.stages) {
                const stages = res.data.stages;
                const phoneVal = res.data.phone || (res.data.cart ? res.data.cart.customer_phone : '') || '';

                let html = `
                    <div class="card border border-primary border-opacity-25 bg-light rounded-3 p-3 mb-3 shadow-xs">
                        <div class="row align-items-center g-2">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fab fa-whatsapp text-success me-1"></i> Target WhatsApp Number (Editable for Testing):
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fas fa-phone-alt text-primary"></i></span>
                                    <input type="text" id="modalOverridePhone" class="form-control bg-white font-monospace fw-bold" value="${htmlEscape(phoneVal)}" placeholder="e.g. 918808714918">
                                </div>
                                <div class="form-text small mt-1" style="font-size: 0.76rem;">
                                    <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Self-Messaging Rule:</span> Agar ye number aapke Meta Business SIM ka hai, toh Meta self-delivery block karta hai. Testing ke liye koi doosra number daal kar test karein!
                                </div>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <span class="badge bg-primary text-white p-2 small text-wrap text-start shadow-xs">
                                    <i class="fas fa-robot me-1"></i> <strong>Cart Recovery Active:</strong>
                                    <div class="small fw-normal text-white-50 mt-0.5">Automated Multi-Stage WhatsApp Follow-ups</div>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                `;

                for (let lvl = 1; lvl <= 4; lvl++) {
                    const st = stages[lvl];
                    if (!st) continue;

                    const isSent = Boolean(st.is_sent);
                    const sentBadge = isSent
                        ? `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Sent (${st.sent_at})</span>`
                        : `<span class="badge bg-secondary"><i class="far fa-clock me-1"></i> Pending / Not Sent</span>`;

                    html += `
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100 ${isSent ? 'border-success bg-light bg-opacity-25' : 'bg-white'}">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="fw-bold text-dark">Stage ${lvl} Reminder</div>
                                    ${sentBadge}
                                </div>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark border small">
                                        <i class="fas fa-file-code text-primary me-1"></i> Template: <strong>${htmlEscape(st.meta_tpl || ('reminder_' + lvl))}</strong>
                                    </span>
                                </div>
                                <div class="small text-muted mb-2 font-monospace p-2 bg-light rounded" style="white-space: pre-wrap; max-height: 110px; overflow-y: auto; font-size: 0.78rem;">${htmlEscape(st.message)}</div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    ${st.wa_link ? `
                                        <a href="${st.wa_link}" target="_blank" class="btn btn-sm btn-outline-success py-1 px-2 rounded-pill" onclick="handleModalWebLinkClick(${cartId}, ${lvl})">
                                            <i class="fab fa-whatsapp me-1"></i> Open Web
                                        </a>
                                    ` : ''}
                                    ${globalWaMode === 'api' ? `
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-pill" onclick="sendModalApiReminder(${cartId}, ${lvl}, this)">
                                            <i class="fas fa-robot me-1"></i> Send via Meta API
                                        </button>
                                    ` : ''}
                                    ${isSent ? `
                                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill" onclick="markStage(${cartId}, ${lvl}, 'unmark_sent', function(){ loadModalStages(${cartId}); loadModalLogs(${cartId}); refreshTableAndStats(); })" title="Reset this stage to unsent">
                                            <i class="fas fa-undo me-1"></i> Reset
                                        </button>
                                    ` : `
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-pill" onclick="markStage(${cartId}, ${lvl}, 'mark_sent', function(){ loadModalStages(${cartId}); loadModalLogs(${cartId}); refreshTableAndStats(); })" title="Mark this stage as sent">
                                            <i class="fas fa-check me-1"></i> Mark Sent
                                        </button>
                                    `}
                                </div>
                            </div>
                        </div>
                    `;
                }
                html += '</div>';
                $('#modalCartStages').html(html);
            } else {
                $('#modalCartStages').html('<div class="small text-danger">Unable to load reminder stages preview.</div>');
            }
        },
        error: function() {
            $('#modalCartStages').html('<div class="small text-danger">Failed to connect to server.</div>');
        }
    });
}

function handleModalWebLinkClick(cartId, level) {
    setTimeout(function() {
        if (confirm(`WhatsApp Web opened in new tab for Stage ${level}!\n\n👉 Did you click 'Send' in WhatsApp Web?\n\nClick [OK] to mark Stage ${level} as SENT.\nClick [Cancel] if you did not send it.`)) {
            markStage(cartId, level, 'mark_sent', function() {
                loadModalStages(cartId);
                loadModalLogs(cartId);
                refreshTableAndStats();
            });
        }
    }, 800);
}

function sendModalApiReminder(cartId, level, btn) {
    const $btn = $(btn);
    const origHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    const overridePhone = ($('#modalOverridePhone').val() || '').trim();

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        data: { action: 'send_reminder', cart_id: cartId, level: level, phone: overridePhone },
        dataType: 'json',
        success: function(res) {
            $btn.html(origHtml).prop('disabled', false);
            if (res.success && res.is_sent) {
                let msg = res.message || `Stage ${level} sent successfully via Meta Cloud API!`;
                if (res.template_used) {
                    msg += `\n(Template: ${res.template_used})`;
                }
                if (res.is_self_send) {
                    msg += `\n\n⚠️ CAUTION: The target phone matches your Meta Sender SIM. Meta drops self-messages silently. Please test with another mobile number!`;
                }
                alert('✅ ' + msg);
                loadModalStages(cartId);
                loadModalLogs(cartId);
                refreshTableAndStats();
            } else {
                const err = res.error || 'Failed to dispatch via Meta Cloud API.';
                alert(`❌ Meta Cloud API Notice:\n\n${err}\n\n💡 Tip: You can click "Open Web" above to send manually via WhatsApp Web.`);
                loadModalStages(cartId);
                loadModalLogs(cartId);
            }
        },
        error: function(xhr, status, errorThrown) {
            $btn.html(origHtml).prop('disabled', false);
            let parsed = null;
            if (xhr.responseText) {
                try {
                    const s = xhr.responseText.indexOf('{');
                    const e = xhr.responseText.lastIndexOf('}');
                    if (s !== -1 && e > s) {
                        parsed = JSON.parse(xhr.responseText.substring(s, e + 1));
                    }
                } catch(err) {}
            }
            if (parsed) {
                if (parsed.success && parsed.is_sent) {
                    let msg = parsed.message || `Stage ${level} sent successfully via Meta Cloud API!`;
                    if (parsed.template_used) msg += `\n(Template: ${parsed.template_used})`;
                    alert('✅ ' + msg);
                    loadModalStages(cartId);
                    loadModalLogs(cartId);
                    refreshTableAndStats();
                    return;
                } else {
                    const err = parsed.error || 'Failed to dispatch via Meta Cloud API.';
                    alert(`❌ Meta Cloud API Notice:\n\n${err}\n\n💡 Tip: You can click "Open Web" above to send manually via WhatsApp Web.`);
                    loadModalStages(cartId);
                    loadModalLogs(cartId);
                    return;
                }
            }
            let errText = 'Network request failed.';
            if (xhr.status) errText += ' (HTTP ' + xhr.status + ')';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errText = xhr.responseJSON.error;
            }
            alert(`❌ ${errText}\n\n💡 Tip: You can click "Open Web" above to send manually via WhatsApp Web.`);
        }
    });
}

function handleRowWhatsAppClick(cartId, btn, customerName, phone) {
    if (globalWaMode === 'web') {
        // Pre-open blank tab synchronously in user gesture stack to prevent Chrome popup blocker
        const waTab = window.open('about:blank', '_blank');
        const $btn = $(btn);
        const origHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

        $.ajax({
            url: 'ajax_abandoned_carts.php',
            type: 'POST',
            cache: false,
            data: { action: 'send_reminder', cart_id: cartId, level: 0, _ts: Date.now() },
            dataType: 'json',
            success: function(res) {
                $btn.html(origHtml).prop('disabled', false);
                if (res.success && res.link) {
                    // Navigate tab to WhatsApp Web link
                    waTab.location.href = res.link;

                    setTimeout(function() {
                        const promptMsg = `WhatsApp Web has been opened in a new tab for:\n${customerName} (+${phone})\n\n` +
                                          `Reminder Stage: Level ${res.level || 'Next'}\n\n` +
                                          `👉 Please click the 'Send' button inside WhatsApp Web to deliver the message.\n\n` +
                                          `Did you send the message to the customer?\n\n` +
                                          `• Click [OK] to mark Stage ${res.level || 1} as SENT in dashboard.\n` +
                                          `• Click [Cancel] if you did not send it yet.`;
                        if (confirm(promptMsg)) {
                            markStage(cartId, res.level || 1, 'mark_sent');
                        }
                    }, 600);
                } else {
                    waTab.close();
                    alert('Error preparing WhatsApp reminder: ' + (res.error || res.message || 'Unknown error'));
                }
            },
            error: function() {
                waTab.close();
                $btn.html(origHtml).prop('disabled', false);
                alert('Request failed. Please check network connection.');
            }
        });
    } else {
        // Meta API Mode
        const $btn = $(btn);
        const origHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

        $.ajax({
            url: 'ajax_abandoned_carts.php',
            type: 'POST',
            cache: false,
            data: { action: 'send_reminder', cart_id: cartId, level: 0, _ts: Date.now() },
            dataType: 'json',
            success: function(res) {
                $btn.html(origHtml).prop('disabled', false);
                if (res.success && res.is_sent) {
                    let alertMsg = `✅ Reminder Level ${res.level || 1} sent successfully via Meta Cloud API!\n\nMessage ID: ${res.message_id || 'OK'}`;
                    if (res.template_used) {
                        alertMsg += `\nTemplate Used: ${res.template_used}`;
                    }
                    if (res.is_self_send) {
                        alertMsg += `\n\n⚠️ CAUTION: Target number matches your Meta Business SIM. Meta drops self-messages silently! Test with an alternate mobile number from Cart Details.`;
                    }
                    alert(alertMsg);
                    refreshTableAndStats();
                } else {
                    const err = res.error || 'Meta Cloud API could not deliver the reminder.';
                    alert(`❌ Meta Cloud API Notice:\n\n${err}\n\n💡 Tip: To send manually via WhatsApp Web, click the [ ↗ ] button next to this cart.`);
                    refreshTableAndStats();
                }
            },
            error: function(xhr, status, errorThrown) {
                $btn.html(origHtml).prop('disabled', false);
                let parsed = null;
                if (xhr.responseText) {
                    try {
                        const s = xhr.responseText.indexOf('{');
                        const e = xhr.responseText.lastIndexOf('}');
                        if (s !== -1 && e > s) {
                            parsed = JSON.parse(xhr.responseText.substring(s, e + 1));
                        }
                    } catch(err) {}
                }
                if (parsed) {
                    if (parsed.success && parsed.is_sent) {
                        let alertMsg = `✅ Reminder Level ${parsed.level || 1} sent successfully via Meta Cloud API!\n\nMessage ID: ${parsed.message_id || 'OK'}`;
                        if (parsed.template_used) alertMsg += `\nTemplate Used: ${parsed.template_used}`;
                        if (parsed.is_self_send) alertMsg += `\n\n⚠️ CAUTION: Target number matches your Meta Business SIM. Meta drops self-messages silently! Test with an alternate mobile number from Cart Details.`;
                        alert(alertMsg);
                        refreshTableAndStats();
                        return;
                    } else {
                        const err = parsed.error || 'Meta Cloud API could not deliver the reminder.';
                        alert(`❌ Meta Cloud API Notice:\n\n${err}\n\n💡 Tip: To send manually via WhatsApp Web, click the [ ↗ ] button next to this cart.`);
                        refreshTableAndStats();
                        return;
                    }
                }
                let errText = 'Server error occurred.';
                if (xhr.status) errText += ' (HTTP ' + xhr.status + ')';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errText = xhr.responseJSON.error;
                }
                alert(`❌ ${errText}\n\n💡 Tip: To send manually via WhatsApp Web, click the [ ↗ ] button next to this cart.`);
            }
        });
    }
}

function handleRowWhatsAppWebClick(cartId, btn, customerName, phone) {
    const waTab = window.open('about:blank', '_blank');
    const $btn = $(btn);
    const origHtml = $btn.html();
    $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        cache: false,
        data: { action: 'get_cart_preview', cart_id: cartId, _ts: Date.now() },
        dataType: 'json',
        success: function(res) {
            $btn.html(origHtml).prop('disabled', false);
            if (res.success && res.data && res.data.stages) {
                // Find next unsent stage or stage 1
                let targetLvl = 1;
                for (let l = 1; l <= 4; l++) {
                    if (!res.data.stages[l].is_sent) {
                        targetLvl = l;
                        break;
                    }
                }
                const link = res.data.stages[targetLvl].wa_link;
                if (link) {
                    waTab.location.href = link;
                    setTimeout(function() {
                        const promptMsg = `WhatsApp Web opened for ${customerName} (+${phone})\n\nReminder Stage: Level ${targetLvl}\n\nDid you click 'Send' in WhatsApp?\n\n• Click [OK] to mark Stage ${targetLvl} as SENT.\n• Click [Cancel] if not sent.`;
                        if (confirm(promptMsg)) {
                            markStage(cartId, targetLvl, 'mark_sent', function() {
                                refreshTableAndStats();
                            });
                        }
                    }, 600);
                } else {
                    waTab.close();
                    alert('Could not generate WhatsApp Web link for this cart.');
                }
            } else {
                waTab.close();
                alert('Could not load cart reminder preview.');
            }
        },
        error: function() {
            waTab.close();
            $btn.html(origHtml).prop('disabled', false);
            alert('Request failed. Please check network.');
        }
    });
}

function markStage(cartId, level, action = 'mark_sent', callback = null) {
    const postAction = (action === 'unmark_sent') ? 'unmark_stage_sent' : 'mark_stage_sent';
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        cache: false,
        data: { action: postAction, cart_id: cartId, level: level, _ts: Date.now() },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                refreshTableAndStats();
                if (callback) callback();
            } else {
                alert(res.error || res.message || 'Error updating stage');
            }
        },
        error: function() {
            alert('Request failed while updating stage.');
        }
    });
}

function loadModalLogs(cartId) {
    $('#modalCartLogs').html('<div class="small text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Loading WhatsApp logs...</div>');

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        cache: false,
        data: { action: 'get_cart_logs', cart_id: cartId, _ts: Date.now() },
        dataType: 'json',
        success: function(res) {
            const logsList = (res && res.success && (res.logs || res.data)) ? (res.logs || res.data) : [];
            if (logsList.length > 0) {
                let logsHtml = '<ul class="list-group list-group-flush small">';
                logsList.forEach(log => {
                    const statusText = log.status || '';
                    let badgeClass = 'bg-secondary';
                    if (statusText.toLowerCase().includes('sent via meta')) badgeClass = 'bg-success';
                    else if (statusText.toLowerCase().includes('failed') || statusText.toLowerCase().includes('error')) badgeClass = 'bg-danger';
                    else if (statusText.toLowerCase().includes('wa.me') || statusText.toLowerCase().includes('reset') || statusText.toLowerCase().includes('manual')) badgeClass = 'bg-warning text-dark';

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

function toggleRawApiLog() {
    const $container = $('#modalRawApiLogContainer');
    if ($container.hasClass('d-none')) {
        $container.removeClass('d-none');
        loadRawApiLog();
    } else {
        $container.addClass('d-none');
    }
}

function loadRawApiLog() {
    $('#modalRawApiLogContent').html('<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Fetching live Meta Graph API log...</span>');
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        cache: false,
        data: { action: 'get_api_log', _ts: Date.now() },
        dataType: 'json',
        success: function(res) {
            if (res && res.success && res.log) {
                $('#modalRawApiLogContent').text(res.log);
                const el = document.getElementById('modalRawApiLogContent');
                if (el) el.scrollTop = el.scrollHeight;
            } else {
                $('#modalRawApiLogContent').html('<span class="text-warning"><i class="fas fa-info-circle me-1"></i> Log file empty or no Meta API calls made yet.</span>');
            }
        },
        error: function() {
            $('#modalRawApiLogContent').html('<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Could not load live API log.</span>');
        }
    });
}

function resetModalReminders() {
    if (currentModalCartId > 0) {
        resetReminders(currentModalCartId, null, function() {
            loadModalStages(currentModalCartId);
            loadModalLogs(currentModalCartId);
        });
    }
}

function resetReminders(cartId, btn, callback = null) {
    if (!confirm(`Reset all reminder stages for Cart #${cartId} back to 0?`)) return;

    const $btn = btn ? $(btn) : null;
    const originalHtml = $btn ? $btn.html() : '';
    if ($btn) $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    // 1. Optimistically update local data and DOM immediately
    const rowEl = document.getElementById(`cart-row-${cartId}`) || (btn ? btn.closest('tr') : null);
    if (rowEl) {
        const tl = rowEl.querySelector('.ac-reminder-timeline');
        if (tl) {
            tl.innerHTML = `
                <div class="ac-reminder-step pending" title="Reminder 1: Not Sent" style="cursor:pointer;" onclick="openCartModalById(${cartId})">1</div>
                <div class="ac-reminder-step pending" title="Reminder 2: Not Sent" style="cursor:pointer;" onclick="openCartModalById(${cartId})">2</div>
                <div class="ac-reminder-step pending" title="Reminder 3: Not Sent" style="cursor:pointer;" onclick="openCartModalById(${cartId})">3</div>
                <div class="ac-reminder-step pending" title="Reminder 4: Not Sent" style="cursor:pointer;" onclick="openCartModalById(${cartId})">4</div>
                <span class="small text-muted fw-bold ms-1">0/4</span>
            `;
        }
    }
    if (currentLoadedCarts[cartId]) {
        currentLoadedCarts[cartId].reminder_step = 0;
        for (let i = 1; i <= 4; i++) {
            currentLoadedCarts[cartId]['reminder_' + i + '_sent'] = null;
        }
    }

    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'POST',
        cache: false,
        data: { action: 'reset_reminders', cart_id: cartId, _ts: Date.now() },
        dataType: 'json',
        success: function(res) {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            if (res.success) {
                refreshTableAndStats();
                if (callback) callback();
            } else {
                alert(res.error || res.message || 'Error resetting reminders');
                refreshTableAndStats();
            }
        },
        error: function(xhr) {
            if ($btn) $btn.html(originalHtml).prop('disabled', false);
            alert('Request failed: ' + (xhr.statusText || 'Network error'));
            refreshTableAndStats();
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

function saveSettings(callback = null) {
    const formEl = document.getElementById('settingsForm');
    if (!formEl) return;
    const formData = new FormData(formEl);
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
                if (typeof callback === 'function') {
                    callback(res);
                } else {
                    alert('Settings saved successfully!');
                }
            } else {
                alert(res.message || 'Error saving settings');
            }
        },
        error: function() {
            alert('Request failed');
        }
    });
}

function applyPresetToAll(tplName, lang = 'en') {
    for (let i = 1; i <= 4; i++) {
        const input = document.getElementById('metaTpl' + i);
        if (input) {
            input.value = tplName;
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 3500);
        }
    }
    const langInput = document.getElementById('metaTplLang');
    if (langInput) {
        langInput.value = lang;
    }

    saveSettings(function(res) {
        if (res.success) {
            alert(`✅ Quick Select Applied!\n\nAll 4 Reminder Stages set to '${tplName}'.\nSettings saved successfully.`);
            location.reload();
        }
    });
}

function applyOfficialCartTemplates() {
    const t1 = document.getElementById('metaTpl1');
    const t2 = document.getElementById('metaTpl2');
    const t3 = document.getElementById('metaTpl3');
    const t4 = document.getElementById('metaTpl4');
    const lang = document.getElementById('metaTplLang');

    if (t1) t1.value = 'reminder_1_gentle_nudge';
    if (t2) t2.value = 'reminder_2_follow_up';
    if (t3) t3.value = 'reminder_3_urgency';
    if (t4) t4.value = 'reminder_4_coupon_discou';
    if (lang) lang.value = 'en';

    saveSettings(function(res) {
        if (res.success) {
            alert("✅ All 4 Official Meta Cart Templates Applied Successfully!\n\n• Stage 1: reminder_1_gentle_nudge\n• Stage 2: reminder_2_follow_up\n• Stage 3: reminder_3_urgency\n• Stage 4: reminder_4_coupon_discou\n• Language: en\n\nSettings saved successfully.");
            location.reload();
        } else {
            alert("Settings saved. Please refresh the page.");
            location.reload();
        }
    });
}

function setTplInput(inputId, tplName) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = tplName;
        input.classList.add('is-valid');
        setTimeout(() => input.classList.remove('is-valid'), 2500);
    }
}

function refreshStats() {
    $.ajax({
        url: 'ajax_abandoned_carts.php',
        type: 'GET',
        cache: false,
        data: { action: 'get_stats', _ts: Date.now() },
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

function openMetaTemplatePicker(targetInputId) {
    currentTplTarget = targetInputId;
    const modalEl = document.getElementById('metaTemplatePickerModal');
    if (modalEl) {
        let modal = null;
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            modal = mdb.Modal.getInstance(modalEl) || new mdb.Modal(modalEl);
        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        }
        if (modal) {
            modal.show();
        } else if (window.jQuery && $(modalEl).modal) {
            $(modalEl).modal('show');
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
        fetchMetaTemplates();
    }
}

function hideMetaTemplatePicker() {
    const modalEl = document.getElementById('metaTemplatePickerModal');
    if (!modalEl) return;
    try {
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            const m = mdb.Modal.getInstance(modalEl);
            if (m) m.hide();
        }
    } catch (e) {}
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
        }
    } catch (e) {}
    if (window.jQuery && $(modalEl).modal) {
        try { $(modalEl).modal('hide'); } catch(e){}
    }
    // Clean up backdrop & classes
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

function fetchMetaTemplates() {
    const statusEl = document.getElementById('modalTplStatus');
    const tbodyEl = document.getElementById('modalTplTableBody');
    const btnRefresh = document.getElementById('btnRefreshModalTpl');

    if (btnRefresh) {
        btnRefresh.disabled = true;
        btnRefresh.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching...';
    }

    if (statusEl) {
        statusEl.className = 'alert alert-info py-2 small';
        statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Connecting to Meta Graph API...';
        statusEl.classList.remove('d-none');
    }

    fetch('ajax_sync_meta_templates.php')
        .then(res => res.json())
        .then(data => {
            if (btnRefresh) {
                btnRefresh.disabled = false;
                btnRefresh.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Refresh from Meta';
            }

            if (data.error) {
                if (statusEl) {
                    statusEl.className = 'alert alert-danger py-3 small';
                    statusEl.innerHTML = `
                        <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i> ${htmlEscape(data.error)}</div>
                        <div class="mt-2">
                            <a href="manage_whatsapp_settings.php" target="_blank" class="btn btn-sm btn-outline-danger fw-bold me-2">
                                <i class="fas fa-cog me-1"></i> Configure WhatsApp Credentials
                            </a>
                            <span class="text-muted">Or use the <strong>Quick Select</strong> buttons above.</span>
                        </div>
                    `;
                }
                if (tbodyEl) {
                    tbodyEl.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <div class="text-danger mb-2"><i class="fas fa-exclamation-circle fs-4"></i></div>
                                <div class="small fw-semibold text-danger">${htmlEscape(data.error)}</div>
                                <div class="small text-muted mt-1">You can also type your template name manually in the box and save.</div>
                            </td>
                        </tr>
                    `;
                }
            } else if (data.templates && data.templates.length > 0) {
                if (statusEl) {
                    statusEl.className = 'alert alert-success py-2 small';
                    statusEl.innerHTML = `<i class="fas fa-check-circle me-1"></i> Found <strong>${data.templates.length}</strong> template(s) in Meta Account!`;
                }

                if (tbodyEl) {
                    tbodyEl.innerHTML = '';
                    data.templates.forEach(tpl => {
                        const isApproved = tpl.status === 'APPROVED';
                        const statusBadge = isApproved 
                            ? '<span class="badge bg-success">APPROVED</span>' 
                            : `<span class="badge bg-warning text-dark">${htmlEscape(tpl.status)}</span>`;

                        const paramBadge = (tpl.param_count && tpl.param_count > 0)
                            ? `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1">${tpl.param_count} params</span>`
                            : '';
                        const headerBadge = (tpl.header_type && tpl.header_type !== 'NONE')
                            ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1"><i class="fas fa-image me-1"></i>${htmlEscape(tpl.header_type)}</span>`
                            : '';

                        const bodyText = tpl.body_text || '';
                        const bodyPreview = bodyText ? `<div class="small text-muted font-monospace mt-1 text-truncate" style="max-width:320px;" title="${htmlEscape(bodyText)}">${htmlEscape(bodyText)}</div>` : '';

                        const row = `
                            <tr class="tpl-row" data-name="${htmlEscape((tpl.name || '').toLowerCase())}">
                                <td>
                                    <div class="fw-bold text-dark d-flex align-items-center">
                                        ${htmlEscape(tpl.name)} ${paramBadge} ${headerBadge}
                                    </div>
                                    ${bodyPreview}
                                </td>
                                <td><span class="badge bg-light text-dark border">${htmlEscape(tpl.language || 'en')}</span></td>
                                <td>${statusBadge}</td>
                                <td class="text-end pe-3">
                                    <button type="button" class="btn btn-sm btn-primary py-1 px-3 rounded-pill" onclick="selectTemplate('${htmlEscape(tpl.name)}', '${htmlEscape(tpl.language || 'en')}')">Select</button>
                                </td>
                            </tr>
                        `;
                        tbodyEl.insertAdjacentHTML('beforeend', row);
                    });
                }
            } else {
                if (statusEl) {
                    statusEl.className = 'alert alert-warning py-2 small';
                    statusEl.innerHTML = '<i class="fas fa-info-circle me-1"></i> No approved templates found in this WhatsApp Business Account.';
                }
                if (tbodyEl) {
                    tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No templates found in Meta account.</td></tr>';
                }
            }
        })
        .catch(err => {
            if (btnRefresh) {
                btnRefresh.disabled = false;
                btnRefresh.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Refresh from Meta';
            }
            if (statusEl) {
                statusEl.className = 'alert alert-danger py-2 small';
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Network error: ' + htmlEscape(err.message);
            }
            if (tbodyEl) {
                tbodyEl.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Network error fetching templates.</td></tr>`;
            }
        });
}

function filterTemplateRows(keyword) {
    const term = (keyword || '').toLowerCase().trim();
    document.querySelectorAll('.tpl-row').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        row.style.display = (!term || name.includes(term)) ? '' : 'none';
    });
}

function selectTemplate(name, lang) {
    if (currentTplTarget) {
        const input = document.getElementById(currentTplTarget);
        if (input) {
            input.value = name;
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 2500);
        }
        if (document.getElementById('metaTplLang')) {
            document.getElementById('metaTplLang').value = lang || 'en';
        }
    }
    hideMetaTemplatePicker();
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
