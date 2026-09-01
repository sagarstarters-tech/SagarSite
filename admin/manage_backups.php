<?php
/**
 * ============================================================
 *  MANAGE BACKUPS — Admin Panel
 *  Location: /admin/manage_backups.php
 * ============================================================
 *  Complete website backup/restore management interface.
 *  Features: Manual Backup (Instant & Custom), Upload & Restore,
 *            Auto Backup (Cron), Download, Delete
 * ============================================================
 */
$current_page = 'manage_backups.php';
include 'admin_header.php';

// Auto-create database tables if not exist (Self-healing migration)
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `site_backups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `backup_name` VARCHAR(255) NOT NULL,
            `backup_type` ENUM('full','db_only','files_only','custom') NOT NULL DEFAULT 'full',
            `trigger_type` ENUM('manual','auto') NOT NULL DEFAULT 'manual',
            `file_path` VARCHAR(500) DEFAULT NULL,
            `file_size` BIGINT UNSIGNED DEFAULT 0,
            `db_tables_count` INT UNSIGNED DEFAULT 0,
            `files_count` INT UNSIGNED DEFAULT 0,
            `status` ENUM('in_progress','completed','failed','restored') NOT NULL DEFAULT 'in_progress',
            `notes` TEXT DEFAULT NULL,
            `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_trigger_type` (`trigger_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $conn->query("CREATE TABLE IF NOT EXISTS `backup_settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $conn->query("INSERT IGNORE INTO `backup_settings` (`setting_key`, `setting_value`) VALUES
            ('auto_backup_enabled', '0'),
            ('auto_backup_frequency', 'weekly'),
            ('auto_backup_type', 'full'),
            ('max_backups_keep', '5'),
            ('last_auto_backup', '0');");
    } catch (\Throwable $e) {
        error_log('[Backup] Auto migration notice: ' . $e->getMessage());
    }
}
?>

<style>
/* ── Backup Manager Styles ─────────────────────────────────── */
.backup-hero {
    background: linear-gradient(135deg, #0d1b2a 0%, #1b2838 40%, #233b52 100%);
    border-radius: 20px;
    padding: 2.2rem 2.5rem;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.backup-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(59,113,202,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.backup-hero::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(20,164,77,0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.backup-hero .hero-content {
    position: relative;
    z-index: 2;
}
.backup-stat-card {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 1.1rem;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease, background 0.3s ease;
}
.backup-stat-card:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,0.12);
}
.backup-stat-card .stat-value {
    font-size: 1.7rem;
    font-weight: 800;
    line-height: 1.2;
}
.backup-stat-card .stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.7;
    margin-top: 0.25rem;
}

/* ── Section Title Card ──────────────────────────────── */
.section-title-badge {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
}

/* ── Action Buttons ──────────────────────────────────── */
.backup-action-btn {
    border: none;
    border-radius: 16px;
    padding: 1.3rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    color: #fff;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.backup-action-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.18);
    color: #fff;
}
.backup-action-btn.btn-full {
    background: linear-gradient(135deg, #14a44d 0%, #0f8a3f 100%);
}
.backup-action-btn.btn-full:hover {
    background: linear-gradient(135deg, #17c45b 0%, #14a44d 100%);
}
.backup-action-btn.btn-db {
    background: linear-gradient(135deg, #3b71ca 0%, #2d5aa8 100%);
}
.backup-action-btn.btn-db:hover {
    background: linear-gradient(135deg, #4a82db 0%, #3b71ca 100%);
}
.backup-action-btn.btn-files {
    background: linear-gradient(135deg, #e4a11b 0%, #cc8f15 100%);
}
.backup-action-btn.btn-files:hover {
    background: linear-gradient(135deg, #f0b42d 0%, #e4a11b 100%);
}
.backup-action-btn.btn-custom-modal {
    background: linear-gradient(135deg, #8a2be2 0%, #6a1b9a 100%);
}
.backup-action-btn.btn-custom-modal:hover {
    background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
}
.backup-action-btn .btn-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}
.backup-action-btn .btn-title {
    font-weight: 700;
    font-size: 0.98rem;
    display: block;
}
.backup-action-btn .btn-desc {
    font-size: 0.73rem;
    opacity: 0.85;
    margin-top: 0.25rem;
    display: block;
}

/* ── Upload Banner Button ────────────────────────────── */
.upload-backup-banner {
    background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%);
    border: 2px dashed #90caf9;
    border-radius: 16px;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}
.upload-backup-banner:hover {
    background: #e1f0fe;
    border-color: #3b71ca;
    transform: translateY(-2px);
}

/* ── Progress Section ─────────────────────────────────── */
.backup-progress-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    display: none;
}
.backup-progress-card.active {
    display: block;
    animation: slideDown 0.4s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-15px); }
    to { opacity: 1; transform: translateY(0); }
}
.progress-ring {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(#14a44d var(--progress, 0%), #e9ecef var(--progress, 0%));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    position: relative;
    transition: background 0.5s ease;
}
.progress-ring-inner {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 800;
    color: #14a44d;
}

/* ── Backup Table ─────────────────────────────────────── */
.backup-table-card {
    border-radius: 16px;
    border: none;
    overflow: hidden;
}
.backup-table-card .card-header {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    padding: 1.25rem 1.5rem;
}
.backup-table {
    margin-bottom: 0;
}
.backup-table thead th {
    background: #f8f9fa;
    border: none;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #6c757d;
    font-weight: 700;
    padding: 0.9rem 1rem;
    white-space: nowrap;
}
.backup-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-color: #f5f5f5;
    font-size: 0.88rem;
}
.backup-table tbody tr {
    transition: background 0.2s ease;
}
.backup-table tbody tr:hover {
    background: #f8fafc;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0.35rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-completed { background: #d4edda; color: #155724; }
.status-failed { background: #f8d7da; color: #721c24; }
.status-in_progress { background: #cce5ff; color: #004085; }
.status-restored { background: #e2d9f3; color: #4a148c; }
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.3rem 0.7rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
}
.type-full { background: #e8f5e9; color: #2e7d32; }
.type-db_only { background: #e3f2fd; color: #1565c0; }
.type-files_only { background: #fff3e0; color: #e65100; }
.type-custom { background: #f3e5f5; color: #7b1fa2; }

.trigger-badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.trigger-manual { background: #e8eaf6; color: #283593; }
.trigger-auto { background: #e0f2f1; color: #00695c; }

.action-btn-group .btn {
    padding: 0.35rem 0.6rem;
    font-size: 0.8rem;
    border-radius: 8px;
}

/* ── Settings Card ────────────────────────────────────── */
.settings-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}
.settings-card .card-body {
    padding: 1.5rem;
}

/* ── Dropzone & Upload Styles ─────────────────────────── */
.dropzone-box {
    border: 2px dashed #3b71ca;
    background: #f8fbff;
    border-radius: 14px;
    padding: 2.5rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.dropzone-box.dragover {
    background: #e3f2fd;
    border-color: #1565c0;
    transform: scale(1.01);
}

/* ── Restore Modal ────────────────────────────────────── */
.restore-warning-box {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    border-radius: 12px;
    padding: 1.2rem;
    border-left: 4px solid #e65100;
}

/* ── Details Modal & Table Formatting ────────────────── */
.modal-details-dialog {
    max-width: 620px;
    margin: 1.75rem auto;
}
.backup-details-table {
    table-layout: fixed;
    width: 100%;
    margin-bottom: 0;
}
.backup-details-table td {
    padding: 0.75rem 0.6rem;
    vertical-align: top;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
}
.backup-details-table td.label-col {
    width: 34%;
    color: #6c757d;
    font-weight: 600;
    font-size: 0.85rem;
}
.backup-details-table td.val-col {
    width: 66%;
    font-size: 0.88rem;
    color: #212529;
}
.backup-name-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    word-break: break-all;
    overflow-wrap: anywhere;
}

/* ── Empty State ──────────────────────────────────────── */
.empty-state {
    padding: 3rem;
    text-align: center;
    color: #adb5bd;
}
.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 767px) {
    .backup-hero { padding: 1.5rem; }
    .backup-stat-card .stat-value { font-size: 1.3rem; }
    .backup-action-btn { padding: 1rem 0.8rem; }
    .backup-action-btn .btn-icon { font-size: 1.5rem; }
    .backup-table-wrapper { overflow-x: auto; }
}
</style>

<div class="container-fluid">

    <!-- ════════ HERO SECTION ════════ -->
    <div class="backup-hero mb-4 mt-1">
        <div class="hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <h3 class="fw-bold mb-2">
                        <i class="fas fa-shield-alt me-2"></i>Backup & Restore Manager
                    </h3>
                    <p class="mb-0 opacity-75" style="font-size:0.92rem;">
                        Complete website backup system — Manual & Automated backups with 1-click restore, local PC download, and secure offline ZIP uploads.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="backup-stat-card">
                                <div class="stat-value text-success" id="statTotalBackups">—</div>
                                <div class="stat-label">Total Backups</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="backup-stat-card">
                                <div class="stat-value text-info" id="statTotalSize">—</div>
                                <div class="stat-label">Storage Used</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="backup-stat-card">
                                <div class="stat-value text-warning" id="statLastBackup">—</div>
                                <div class="stat-label">Last Backup</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════ MANUAL BACKUP CONTROLS ════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-hand-pointer me-2 text-primary"></i>Manual Backup Options (मैन्युअल बैकअप)
                    </h5>
                    <span class="badge bg-primary section-title-badge">Instant 1-Click</span>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 fw-semibold" onclick="showUploadModal()">
                        <i class="fas fa-upload me-1"></i> Upload Backup ZIP from PC
                    </button>
                </div>
            </div>

            <!-- Quick Action 4-Grid Buttons -->
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <button class="backup-action-btn btn-full" onclick="createBackup('full')" id="btnFullBackup" title="Create complete site backup">
                        <span class="btn-icon"><i class="fas fa-database"></i> <i class="fas fa-plus" style="font-size:0.8rem;"></i></span>
                        <span class="btn-title">Full Manual Backup</span>
                        <span class="btn-desc">Database + All Files & Media</span>
                    </button>
                </div>
                <div class="col-6 col-lg-3">
                    <button class="backup-action-btn btn-db" onclick="createBackup('db_only')" id="btnDbBackup" title="Create database SQL dump">
                        <span class="btn-icon"><i class="fas fa-server"></i></span>
                        <span class="btn-title">Database Only</span>
                        <span class="btn-desc">All MySQL Tables & Data (.sql)</span>
                    </button>
                </div>
                <div class="col-6 col-lg-3">
                    <button class="backup-action-btn btn-files" onclick="createBackup('files_only')" id="btnFilesBackup" title="Backup uploads and themes">
                        <span class="btn-icon"><i class="fas fa-folder-open"></i></span>
                        <span class="btn-title">Files & Media Only</span>
                        <span class="btn-desc">Uploads, Assets, CSS & Config</span>
                    </button>
                </div>
                <div class="col-6 col-lg-3">
                    <button class="backup-action-btn btn-custom-modal" onclick="showCustomModal()" id="btnCustomBackup" title="Choose custom items and name">
                        <span class="btn-icon"><i class="fas fa-sliders-h"></i></span>
                        <span class="btn-title">Custom Manual Backup</span>
                        <span class="btn-desc">Custom Name, Notes & Selection</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════ PROGRESS CARD (Hidden by default) ════════ -->
    <div class="backup-progress-card mb-4" id="progressCard">
        <div class="text-center">
            <div class="progress-ring" id="progressRing" style="--progress: 0%">
                <div class="progress-ring-inner" id="progressPercent">0%</div>
            </div>
            <h5 class="fw-bold" id="progressTitle">Creating Backup...</h5>
            <p class="text-muted small" id="progressSubtext">Please wait, do not close this page.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- ════════ BACKUP HISTORY TABLE (LEFT) ════════ -->
        <div class="col-lg-8">
            <div class="card backup-table-card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Backup History & Storage</h5>
                    <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="loadBackups()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="backup-table-wrapper">
                        <table class="table backup-table mb-0">
                            <thead>
                                <tr>
                                    <th>Backup Details</th>
                                    <th>Type</th>
                                    <th>Trigger</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupTableBody">
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-cloud-upload-alt d-block"></i>
                                        <p class="mb-0">Loading backups...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="paginationBar" style="display:none !important;">
                        <span class="text-muted small" id="paginationInfo"></span>
                        <div class="btn-group btn-group-sm" id="paginationBtns"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════ AUTO BACKUP SETTINGS (RIGHT) ════════ -->
        <div class="col-lg-4">
            <div class="card settings-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-clock me-2 text-warning"></i>Auto Backup (Cron)</h5>
                        <span class="badge bg-warning text-dark" style="font-size:0.7rem;">Automated</span>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="autoBackupEnabled" style="width:3rem;height:1.5rem;">
                        <label class="form-check-label fw-semibold ms-2" for="autoBackupEnabled">Enable Auto Backup</label>
                    </div>

                    <div id="autoBackupOptions">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Frequency</label>
                            <select class="form-select rounded-3" id="autoBackupFrequency">
                                <option value="daily">Daily (Every 24 Hours)</option>
                                <option value="weekly" selected>Weekly (Every 7 Days)</option>
                                <option value="monthly">Monthly (Every 30 Days)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Backup Type</label>
                            <select class="form-select rounded-3" id="autoBackupType">
                                <option value="full">Full (DB + All Files)</option>
                                <option value="db_only">Database Only (.sql)</option>
                                <option value="files_only">Files Only (Uploads + Assets)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Max Backups to Keep</label>
                            <input type="number" class="form-control rounded-3" id="maxBackupsKeep" min="1" max="50" value="5">
                            <div class="form-text">Older backups will be auto-deleted to save storage.</div>
                        </div>

                        <button class="btn btn-primary w-100 rounded-pill btn-custom" onclick="saveAutoSettings()" id="btnSaveSettings">
                            <i class="fas fa-save me-2"></i>Save Auto Settings
                        </button>
                    </div>

                    <!-- Last auto backup info -->
                    <div class="mt-3 p-3 rounded-3" style="background: #f8f9fa;" id="lastAutoInfo">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle text-muted"></i>
                            <span class="small text-muted" id="lastAutoText">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Info Card -->
            <div class="card settings-card mt-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-info"></i>What Gets Backed Up</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2">
                            <i class="fas fa-database text-primary me-2"></i>
                            <strong>Database:</strong> All MySQL tables, data & structure
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-images text-success me-2"></i>
                            <strong>Uploads:</strong> Media, product images & downloads
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-palette text-warning me-2"></i>
                            <strong>Assets:</strong> CSS, JS & design icons
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-cog text-danger me-2"></i>
                            <strong>Config:</strong> .env, .htaccess, robots & settings
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════ CUSTOM MANUAL BACKUP MODAL ════════ -->
<div class="modal fade" id="customBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-sliders-h me-2 text-primary"></i>Custom Manual Backup
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="text-muted small">Configure exact components, custom backup label, and remarks.</p>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Backup Label / Name (Optional)</label>
                    <input type="text" class="form-control rounded-3" id="customBackupName" placeholder="e.g. before_mega_sale_update">
                    <div class="form-text">Timestamp will be appended automatically.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Select Components to Include</label>
                    <div class="card p-3 bg-light border-0 rounded-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="customIncDb" checked>
                            <label class="form-check-label fw-semibold" for="customIncDb">
                                <i class="fas fa-database text-primary me-2"></i>MySQL Database (All tables & records)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="customIncUploads" checked>
                            <label class="form-check-label fw-semibold" for="customIncUploads">
                                <i class="fas fa-images text-success me-2"></i>Uploads & Media Files (<code>uploads/</code>)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="customIncAssets" checked>
                            <label class="form-check-label fw-semibold" for="customIncAssets">
                                <i class="fas fa-palette text-warning me-2"></i>Assets & Styles (<code>assets/</code>)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="customIncConfig" checked>
                            <label class="form-check-label fw-semibold" for="customIncConfig">
                                <i class="fas fa-cog text-danger me-2"></i>Config & Settings (<code>config/</code>, <code>.env</code>, <code>.htaccess</code>)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Notes / Reason (Optional)</label>
                    <textarea class="form-control rounded-3" id="customBackupNotes" rows="2" placeholder="e.g. Manual backup before catalog bulk price update"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 btn-custom" id="btnExecuteCustom" onclick="executeCustomBackup()">
                    <i class="fas fa-rocket me-2"></i>Start Manual Backup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════ UPLOAD BACKUP ZIP MODAL ════════ -->
<div class="modal fade" id="uploadBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Upload Backup ZIP from Computer
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="text-muted small">Upload an existing backup <code>.zip</code> file previously downloaded from this site.</p>

                <div class="dropzone-box mb-3" id="dropzoneBox" onclick="document.getElementById('backupZipInput').click()">
                    <i class="fas fa-file-archive fa-3x text-primary mb-2 d-block"></i>
                    <h6 class="fw-bold mb-1" id="dropzoneTitle">Click or Drag & Drop Backup .ZIP file here</h6>
                    <p class="text-muted small mb-0" id="dropzoneSub">Maximum upload size depends on server php.ini</p>
                    <input type="file" id="backupZipInput" accept=".zip" style="display:none;" onchange="handleFileSelected(this)">
                </div>

                <div id="selectedFileInfo" class="p-3 bg-light rounded-3 d-none mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fw-bold" id="selectedFileName">—</span>
                            <div class="small text-muted" id="selectedFileSize">—</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="clearSelectedFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="progress rounded-pill mb-3 d-none" id="uploadProgressContainer" style="height: 10px;">
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width: 0%"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 btn-custom" id="btnConfirmUpload" onclick="executeUploadBackup()" disabled>
                    <i class="fas fa-upload me-2"></i>Upload Backup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════ RESTORE CONFIRMATION MODAL ════════ -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-undo-alt me-2 text-warning"></i>Restore Backup
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <div class="restore-warning-box mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-warning mt-1"></i>
                        <div>
                            <strong>Warning:</strong> Restoring will overwrite current site data. An automatic safety snapshot will be created before restore starts.
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="p-3 rounded-3 bg-light">
                        <div class="small text-muted">Restoring from:</div>
                        <div class="fw-bold" id="restoreBackupName">—</div>
                        <div class="small text-muted" id="restoreBackupDate">—</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">What to restore:</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="restoreDbCheck" checked>
                        <label class="form-check-label" for="restoreDbCheck">
                            <i class="fas fa-database text-primary me-1"></i> Database (tables & data)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="restoreFilesCheck" checked>
                        <label class="form-check-label" for="restoreFilesCheck">
                            <i class="fas fa-folder text-success me-1"></i> Files (uploads, assets, config)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 text-white" id="confirmRestoreBtn" onclick="executeRestore()">
                    <i class="fas fa-undo-alt me-2"></i>Restore Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════ DELETE CONFIRMATION MODAL ════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fas fa-trash-alt fa-3x text-danger"></i>
                </div>
                <h5 class="fw-bold">Delete Backup?</h5>
                <p class="text-muted small">This action cannot be undone. The backup archive will be permanently removed.</p>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn" onclick="executeDelete()">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════ BACKUP DETAILS MODAL ════════ -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-details-dialog">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Backup Details</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4" id="detailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// ═══════════════════════════════════════════════════════════
//  BACKUP MANAGER — Frontend JS
// ═══════════════════════════════════════════════════════════

const CSRF_TOKEN = '<?php echo csrf_token(); ?>';
let currentPage = 1;
let restoreBackupId = null;
let deleteBackupId = null;

// ── Modal instances ─────────────────────────────────────────
let restoreModalInstance, deleteModalInstance, detailsModalInstance, customModalInstance, uploadModalInstance;

document.addEventListener('DOMContentLoaded', function() {
    restoreModalInstance = new mdb.Modal(document.getElementById('restoreModal'));
    deleteModalInstance = new mdb.Modal(document.getElementById('deleteModal'));
    detailsModalInstance = new mdb.Modal(document.getElementById('detailsModal'));
    customModalInstance = new mdb.Modal(document.getElementById('customBackupModal'));
    uploadModalInstance = new mdb.Modal(document.getElementById('uploadBackupModal'));

    initDropzone();
    loadBackups();
    loadAutoSettings();
});


// ═══════════════════════════════════════════════════════════
//  1-CLICK MANUAL BACKUP
// ═══════════════════════════════════════════════════════════
function createBackup(type, customName = '', customNotes = '') {
    // Disable all action buttons
    document.querySelectorAll('.backup-action-btn').forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.6';
        b.style.pointerEvents = 'none';
    });

    // Show progress card
    const progressCard = document.getElementById('progressCard');
    progressCard.classList.add('active');
    
    const typeLabels = { full: 'Full Manual', db_only: 'Database Only', files_only: 'Files Only', custom: 'Custom Manual' };
    document.getElementById('progressTitle').textContent = `Creating ${typeLabels[type] || 'Manual'} Backup...`;
    document.getElementById('progressSubtext').textContent = 'Compressing database & files. Please wait, do not close this page.';

    // Animate progress ring
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress = Math.min(progress + Math.random() * 8, 90);
        updateProgressRing(progress);
    }, 450);

    const formData = new FormData();
    formData.append('action', 'create_backup');
    formData.append('backup_type', type);
    if (customName) formData.append('backup_name', customName);
    if (customNotes) formData.append('notes', customNotes);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            clearInterval(progressInterval);
            
            if (data.success) {
                updateProgressRing(100);
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Manual Backup Created!';
                document.getElementById('progressSubtext').innerHTML = `
                    <span class="text-success fw-bold">${data.backup.name}</span><br>
                    <span class="text-muted">Size: ${data.backup.size} | Tables: ${data.backup.tables} | Files: ${data.backup.files}</span>
                `;
                document.getElementById('progressRing').style.background = `conic-gradient(#14a44d 100%, #e9ecef 100%)`;
                showToast('success', 'Manual backup completed successfully!');
                loadBackups();
            } else {
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Backup Failed';
                document.getElementById('progressSubtext').innerHTML = `<span class="text-danger">${data.error || 'Unknown error occurred.'}</span>`;
                document.getElementById('progressRing').style.background = `conic-gradient(#dc4c64 100%, #e9ecef 100%)`;
                document.getElementById('progressPercent').innerHTML = '<i class="fas fa-times text-danger"></i>';
                showToast('danger', data.error || 'Manual backup failed.');
            }
        })
        .catch(err => {
            clearInterval(progressInterval);
            document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Connection Error';
            document.getElementById('progressSubtext').textContent = err.message;
            showToast('danger', err.message);
        })
        .finally(() => {
            setTimeout(() => {
                document.querySelectorAll('.backup-action-btn').forEach(b => {
                    b.disabled = false;
                    b.style.opacity = '1';
                    b.style.pointerEvents = 'auto';
                });
                setTimeout(() => {
                    progressCard.classList.remove('active');
                    updateProgressRing(0);
                }, 5000);
            }, 1500);
        });
}

function updateProgressRing(pct) {
    const ring = document.getElementById('progressRing');
    const percentEl = document.getElementById('progressPercent');
    if (!ring || !percentEl) return;
    ring.style.setProperty('--progress', pct + '%');
    ring.style.background = `conic-gradient(#14a44d ${pct}%, #e9ecef ${pct}%)`;
    percentEl.textContent = Math.round(pct) + '%';
}


// ═══════════════════════════════════════════════════════════
//  CUSTOM MANUAL BACKUP MODAL & EXECUTION
// ═══════════════════════════════════════════════════════════
function showCustomModal() {
    document.getElementById('customBackupName').value = '';
    document.getElementById('customBackupNotes').value = '';
    document.getElementById('customIncDb').checked = true;
    document.getElementById('customIncUploads').checked = true;
    document.getElementById('customIncAssets').checked = true;
    document.getElementById('customIncConfig').checked = true;
    customModalInstance.show();
}

function executeCustomBackup() {
    const incDb = document.getElementById('customIncDb').checked;
    const incUploads = document.getElementById('customIncUploads').checked;
    const incAssets = document.getElementById('customIncAssets').checked;
    const incConfig = document.getElementById('customIncConfig').checked;

    if (!incDb && !incUploads && !incAssets && !incConfig) {
        showToast('warning', 'Please select at least one component to backup.');
        return;
    }

    const customName = document.getElementById('customBackupName').value.trim();
    const customNotes = document.getElementById('customBackupNotes').value.trim();

    customModalInstance.hide();

    // Disable buttons
    document.querySelectorAll('.backup-action-btn').forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.6';
        b.style.pointerEvents = 'none';
    });

    const progressCard = document.getElementById('progressCard');
    progressCard.classList.add('active');
    document.getElementById('progressTitle').textContent = 'Creating Custom Manual Backup...';
    document.getElementById('progressSubtext').textContent = 'Packaging selected components. Please wait...';

    let progress = 0;
    const progressInterval = setInterval(() => {
        progress = Math.min(progress + Math.random() * 8, 90);
        updateProgressRing(progress);
    }, 450);

    const formData = new FormData();
    formData.append('action', 'create_backup');
    formData.append('backup_type', 'custom');
    formData.append('backup_name', customName);
    formData.append('notes', customNotes);
    formData.append('include_db', incDb ? '1' : '0');
    formData.append('include_uploads', incUploads ? '1' : '0');
    formData.append('include_assets', incAssets ? '1' : '0');
    formData.append('include_config', incConfig ? '1' : '0');
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            clearInterval(progressInterval);
            if (data.success) {
                updateProgressRing(100);
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Custom Backup Created!';
                document.getElementById('progressSubtext').innerHTML = `
                    <span class="text-success fw-bold">${data.backup.name}</span><br>
                    <span class="text-muted">Size: ${data.backup.size} | Tables: ${data.backup.tables} | Files: ${data.backup.files}</span>
                `;
                document.getElementById('progressRing').style.background = `conic-gradient(#14a44d 100%, #e9ecef 100%)`;
                showToast('success', 'Custom manual backup created successfully!');
                loadBackups();
            } else {
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Backup Failed';
                document.getElementById('progressSubtext').innerHTML = `<span class="text-danger">${data.error || 'Unknown error occurred.'}</span>`;
                document.getElementById('progressRing').style.background = `conic-gradient(#dc4c64 100%, #e9ecef 100%)`;
                showToast('danger', data.error || 'Custom backup failed.');
            }
        })
        .catch(err => {
            clearInterval(progressInterval);
            document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Connection Error';
            document.getElementById('progressSubtext').textContent = err.message;
            showToast('danger', err.message);
        })
        .finally(() => {
            setTimeout(() => {
                document.querySelectorAll('.backup-action-btn').forEach(b => {
                    b.disabled = false;
                    b.style.opacity = '1';
                    b.style.pointerEvents = 'auto';
                });
                setTimeout(() => {
                    progressCard.classList.remove('active');
                    updateProgressRing(0);
                }, 5000);
            }, 1500);
        });
}


// ═══════════════════════════════════════════════════════════
//  UPLOAD MANUAL BACKUP (.ZIP)
// ═══════════════════════════════════════════════════════════
function showUploadModal() {
    clearSelectedFile();
    uploadModalInstance.show();
}

function initDropzone() {
    const dropzone = document.getElementById('dropzoneBox');
    if (!dropzone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const input = document.getElementById('backupZipInput');
            input.files = files;
            handleFileSelected(input);
        }
    });
}

function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (!file.name.toLowerCase().endsWith('.zip')) {
            showToast('danger', 'Please select a valid .zip backup archive.');
            clearSelectedFile();
            return;
        }

        document.getElementById('selectedFileName').textContent = file.name;
        document.getElementById('selectedFileSize').textContent = formatBytes(file.size);
        document.getElementById('selectedFileInfo').classList.remove('d-none');
        document.getElementById('dropzoneBox').classList.add('d-none');
        document.getElementById('btnConfirmUpload').disabled = false;
    }
}

function clearSelectedFile() {
    const input = document.getElementById('backupZipInput');
    if (input) input.value = '';
    document.getElementById('selectedFileInfo').classList.add('d-none');
    document.getElementById('dropzoneBox').classList.remove('d-none');
    document.getElementById('btnConfirmUpload').disabled = true;
    document.getElementById('uploadProgressContainer').classList.add('d-none');
    document.getElementById('uploadProgressBar').style.width = '0%';
}

function executeUploadBackup() {
    const input = document.getElementById('backupZipInput');
    if (!input.files || !input.files[0]) return;

    const btn = document.getElementById('btnConfirmUpload');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';

    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    progressContainer.classList.remove('d-none');
    progressBar.style.width = '10%';

    const formData = new FormData();
    formData.append('action', 'upload_backup');
    formData.append('backup_file', input.files[0]);
    formData.append('_csrf_token', CSRF_TOKEN);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_backup.php', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + '%';
        }
    };

    xhr.onload = function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload Backup';

        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                uploadModalInstance.hide();
                showToast('success', data.message || 'Backup archive uploaded successfully!');
                loadBackups();
            } else {
                showToast('danger', data.error || 'Upload failed.');
            }
        } catch (e) {
            showToast('danger', 'Server returned invalid response: ' + xhr.responseText.substring(0, 100));
        }
    };

    xhr.onerror = function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload Backup';
        showToast('danger', 'Network error during upload.');
    };

    xhr.send(formData);
}


// ═══════════════════════════════════════════════════════════
//  LOAD BACKUPS TABLE
// ═══════════════════════════════════════════════════════════
function loadBackups(page) {
    page = page || 1;
    currentPage = page;

    const formData = new FormData();
    formData.append('action', 'list_backups');
    formData.append('page', page);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('backupTableBody').innerHTML = 
                    '<tr><td colspan="7" class="text-center text-danger py-4">Error loading backups.</td></tr>';
                return;
            }

            const tbody = document.getElementById('backupTableBody');
            
            if (data.backups.length === 0) {
                tbody.innerHTML = `
                    <tr><td colspan="7" class="empty-state">
                        <i class="fas fa-cloud-upload-alt d-block"></i>
                        <h6 class="mt-2 mb-1">No Backups Yet</h6>
                        <p class="mb-0 small">Click one of the buttons above to create your first manual backup.</p>
                    </td></tr>`;
                document.getElementById('paginationBar').style.display = 'none !important';
            } else {
                let html = '';
                let totalSize = 0;
                let lastDate = '—';

                data.backups.forEach((b, i) => {
                    totalSize += parseInt(b.file_size || 0);
                    if (i === 0) lastDate = b.created_at_formatted;

                    const statusIcon = {
                        'completed': 'fa-check-circle',
                        'failed': 'fa-times-circle',
                        'in_progress': 'fa-spinner fa-spin',
                        'restored': 'fa-undo'
                    }[b.status] || 'fa-circle';

                    const typeIcon = {
                        'full': 'fa-database',
                        'db_only': 'fa-server',
                        'files_only': 'fa-folder',
                        'custom': 'fa-sliders-h'
                    }[b.backup_type] || 'fa-file';

                    const typeLabel = {
                        'full': 'Full',
                        'db_only': 'DB Only',
                        'files_only': 'Files Only',
                        'custom': 'Custom'
                    }[b.backup_type] || b.backup_type;

                    const triggerLabel = b.trigger_type === 'auto' ? 'AUTO (CRON)' : 'MANUAL';
                    const triggerClass = b.trigger_type === 'auto' ? 'trigger-auto' : 'trigger-manual';

                    html += `
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:0.85rem;">${escHtml(b.backup_name)}</div>
                            <div class="text-muted" style="font-size:0.72rem;">
                                ${b.created_by_name ? 'by ' + escHtml(b.created_by_name) : ''}
                                ${b.db_tables_count > 0 ? ' · ' + b.db_tables_count + ' tables' : ''}
                                ${b.files_count > 0 ? ' · ' + b.files_count + ' files' : ''}
                            </div>
                        </td>
                        <td><span class="type-badge type-${b.backup_type}"><i class="fas ${typeIcon}"></i> ${typeLabel}</span></td>
                        <td><span class="trigger-badge ${triggerClass}">${triggerLabel}</span></td>
                        <td>
                            <span class="fw-semibold">${b.file_size_formatted}</span>
                            ${!b.file_exists && b.status === 'completed' ? '<br><span class="text-danger" style="font-size:0.7rem;"><i class="fas fa-exclamation-triangle"></i> Missing</span>' : ''}
                        </td>
                        <td><span class="status-badge status-${b.status}"><i class="fas ${statusIcon}"></i> ${capitalize(b.status)}</span></td>
                        <td><span style="font-size:0.82rem;">${b.created_at_formatted}</span></td>
                        <td>
                            <div class="action-btn-group d-flex gap-1 justify-content-center flex-wrap">
                                ${b.file_exists && (b.status === 'completed' || b.status === 'restored') ? `
                                    <a href="ajax_backup.php?action=download_backup&id=${b.id}" class="btn btn-sm btn-outline-primary" title="Download ZIP to Computer">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning" title="Restore" onclick="showRestore(${b.id}, '${escJs(b.backup_name)}', '${escJs(b.created_at_formatted)}', '${b.backup_type}')">
                                        <i class="fas fa-undo-alt"></i>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-info" title="Details" onclick="showDetails(${b.id})">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="showDelete(${b.id})">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });

                tbody.innerHTML = html;

                // Update hero stats
                document.getElementById('statTotalBackups').textContent = data.total;
                document.getElementById('statTotalSize').textContent = formatBytes(totalSize);
                document.getElementById('statLastBackup').textContent = lastDate.split(',')[0] || '—';
            }

            renderPagination(data.page, data.pages, data.total);
        })
        .catch(err => {
            document.getElementById('backupTableBody').innerHTML = 
                `<tr><td colspan="7" class="text-center text-danger py-4">Failed to load: ${err.message}</td></tr>`;
        });
}


// ═══════════════════════════════════════════════════════════
//  RESTORE
// ═══════════════════════════════════════════════════════════
function showRestore(id, name, date, type) {
    restoreBackupId = id;
    document.getElementById('restoreBackupName').textContent = name;
    document.getElementById('restoreBackupDate').textContent = date;
    
    document.getElementById('restoreDbCheck').checked = (type === 'full' || type === 'db_only' || type === 'custom');
    document.getElementById('restoreFilesCheck').checked = (type === 'full' || type === 'files_only' || type === 'custom');
    document.getElementById('restoreDbCheck').disabled = (type === 'files_only');
    document.getElementById('restoreFilesCheck').disabled = (type === 'db_only');

    restoreModalInstance.show();
}

function executeRestore() {
    if (!restoreBackupId) return;

    const btn = document.getElementById('confirmRestoreBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restoring...';

    const formData = new FormData();
    formData.append('action', 'restore_backup');
    formData.append('id', restoreBackupId);
    formData.append('restore_db', document.getElementById('restoreDbCheck').checked ? '1' : '0');
    formData.append('restore_files', document.getElementById('restoreFilesCheck').checked ? '1' : '0');
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            restoreModalInstance.hide();
            if (data.success) {
                showToast('success', data.message || 'Restore completed successfully!');
                loadBackups();
            } else {
                showToast('danger', data.error || 'Restore failed.');
            }
        })
        .catch(err => {
            restoreModalInstance.hide();
            showToast('danger', 'Connection error: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-undo-alt me-2"></i>Restore Now';
            restoreBackupId = null;
        });
}


// ═══════════════════════════════════════════════════════════
//  DELETE
// ═══════════════════════════════════════════════════════════
function showDelete(id) {
    deleteBackupId = id;
    deleteModalInstance.show();
}

function executeDelete() {
    if (!deleteBackupId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

    const formData = new FormData();
    formData.append('action', 'delete_backup');
    formData.append('id', deleteBackupId);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            deleteModalInstance.hide();
            if (data.success) {
                showToast('success', 'Backup deleted successfully.');
                loadBackups();
            } else {
                showToast('danger', data.error || 'Delete failed.');
            }
        })
        .catch(err => {
            deleteModalInstance.hide();
            showToast('danger', 'Connection error: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
            deleteBackupId = null;
        });
}


// ═══════════════════════════════════════════════════════════
//  DETAILS MODAL
// ═══════════════════════════════════════════════════════════
function showDetails(id) {
    document.getElementById('detailsContent').innerHTML = 
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="small text-muted mt-2">Loading details...</div></div>';
    detailsModalInstance.show();

    const formData = new FormData();
    formData.append('action', 'get_backup_info');
    formData.append('id', id);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const b = data.backup;
                const typeIcon = {
                    'full': 'fa-database',
                    'db_only': 'fa-server',
                    'files_only': 'fa-folder',
                    'custom': 'fa-sliders-h'
                }[b.backup_type] || 'fa-file';

                const statusIcon = {
                    'completed': 'fa-check-circle',
                    'failed': 'fa-times-circle',
                    'in_progress': 'fa-spinner fa-spin',
                    'restored': 'fa-undo'
                }[b.status] || 'fa-circle';

                const triggerLabel = b.trigger_type === 'auto' ? 'AUTO (CRON)' : 'MANUAL';
                const triggerClass = b.trigger_type === 'auto' ? 'trigger-auto' : 'trigger-manual';

                document.getElementById('detailsContent').innerHTML = `
                    <div class="backup-name-card mb-3">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;letter-spacing:0.5px;">Backup File Name</span>
                                <div class="fw-bold fs-6 text-dark mt-1" style="word-break:break-all;">${escHtml(b.backup_name)}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 flex-shrink-0" onclick="navigator.clipboard.writeText('${escJs(b.backup_name)}'); showToast('info', 'Backup name copied to clipboard!');" title="Copy backup name">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <table class="table backup-details-table table-borderless">
                        <tr>
                            <td class="label-col"><i class="fas fa-tag me-1 text-primary"></i> Type</td>
                            <td class="val-col"><span class="type-badge type-${b.backup_type}"><i class="fas ${typeIcon}"></i> ${capitalize(b.backup_type.replace('_',' '))}</span></td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-bolt me-1 text-warning"></i> Trigger</td>
                            <td class="val-col"><span class="trigger-badge ${triggerClass}">${triggerLabel}</span></td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-check-circle me-1 text-success"></i> Status</td>
                            <td class="val-col"><span class="status-badge status-${b.status}"><i class="fas ${statusIcon}"></i> ${capitalize(b.status)}</span></td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-hdd me-1 text-info"></i> File Size</td>
                            <td class="val-col fw-bold">${b.file_size_formatted}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-database me-1 text-primary"></i> DB Tables</td>
                            <td class="val-col">${b.db_tables_count > 0 ? b.db_tables_count + ' tables' : '—'}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-folder me-1 text-warning"></i> Files Backed Up</td>
                            <td class="val-col">${b.files_count > 0 ? b.files_count + ' files' : '—'}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-calendar-alt me-1 text-muted"></i> Created At</td>
                            <td class="val-col">${b.created_at_formatted}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-user me-1 text-muted"></i> Created By</td>
                            <td class="val-col">${escHtml(b.created_by_name || 'System / Auto Cron')}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><i class="fas fa-shield-alt me-1 text-muted"></i> Storage Status</td>
                            <td class="val-col">${b.file_exists ? '<span class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i> Verified on Disk</span>' : '<span class="text-danger fw-semibold"><i class="fas fa-times-circle me-1"></i> File Missing on Disk</span>'}</td>
                        </tr>
                        ${b.notes ? `
                        <tr>
                            <td class="label-col"><i class="fas fa-sticky-note me-1 text-muted"></i> Notes</td>
                            <td class="val-col">
                                <div class="p-2 rounded-3 bg-light text-muted border small" style="word-break:break-word;">
                                    ${escHtml(b.notes)}
                                </div>
                            </td>
                        </tr>` : ''}
                    </table>
                `;
            } else {
                document.getElementById('detailsContent').innerHTML = `<p class="text-danger py-3 text-center">${data.error}</p>`;
            }
        });
}


// ═══════════════════════════════════════════════════════════
//  AUTO BACKUP SETTINGS
// ═══════════════════════════════════════════════════════════
function loadAutoSettings() {
    const formData = new FormData();
    formData.append('action', 'get_settings');
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const s = data.settings;
                document.getElementById('autoBackupEnabled').checked = s.auto_backup_enabled === '1';
                document.getElementById('autoBackupFrequency').value = s.auto_backup_frequency;
                document.getElementById('autoBackupType').value = s.auto_backup_type;
                document.getElementById('maxBackupsKeep').value = s.max_backups_keep;

                if (s.last_auto_backup && s.last_auto_backup !== '0') {
                    const d = new Date(parseInt(s.last_auto_backup) * 1000);
                    document.getElementById('lastAutoText').textContent = 'Last auto backup: ' + d.toLocaleString('en-IN');
                } else {
                    document.getElementById('lastAutoText').textContent = 'No auto backups have been run yet.';
                }
            }
        })
        .catch(() => {});
}

function saveAutoSettings() {
    const btn = document.getElementById('btnSaveSettings');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    const formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('auto_backup_enabled', document.getElementById('autoBackupEnabled').checked ? '1' : '0');
    formData.append('auto_backup_frequency', document.getElementById('autoBackupFrequency').value);
    formData.append('auto_backup_type', document.getElementById('autoBackupType').value);
    formData.append('max_backups_keep', document.getElementById('maxBackupsKeep').value);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Auto-backup settings saved!');
            } else {
                showToast('danger', data.error || 'Failed to save settings.');
            }
        })
        .catch(err => showToast('danger', 'Error: ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Auto Settings';
        });
}


// ═══════════════════════════════════════════════════════════
//  PAGINATION
// ═══════════════════════════════════════════════════════════
function renderPagination(current, total, totalRecords) {
    const bar = document.getElementById('paginationBar');
    const info = document.getElementById('paginationInfo');
    const btns = document.getElementById('paginationBtns');

    if (total <= 1) {
        bar.style.display = 'none';
        return;
    }

    bar.style.display = '';
    bar.style.cssText = '';
    info.textContent = `Page ${current} of ${total} (${totalRecords} backups)`;

    let html = '';
    html += `<button class="btn btn-outline-primary ${current === 1 ? 'disabled' : ''}" onclick="loadBackups(${current-1})"><i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = 1; i <= total; i++) {
        if (total > 7 && i > 3 && i < total - 2 && Math.abs(i - current) > 1) {
            if (i === 4) html += `<button class="btn btn-outline-primary disabled">...</button>`;
            continue;
        }
        html += `<button class="btn ${i === current ? 'btn-primary' : 'btn-outline-primary'}" onclick="loadBackups(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-outline-primary ${current === total ? 'disabled' : ''}" onclick="loadBackups(${current+1})"><i class="fas fa-chevron-right"></i></button>`;
    btns.innerHTML = html;
}


// ═══════════════════════════════════════════════════════════
//  UTILITY HELPERS
// ═══════════════════════════════════════════════════════════
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escJs(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ') : '';
}

function formatBytes(bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

function showToast(type, message) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible position-fixed shadow-lg rounded-3 py-2 px-4`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; animation: slideDown 0.3s ease;';
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${escHtml(message)}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}
</script>

<?php include 'admin_footer.php'; ?>
