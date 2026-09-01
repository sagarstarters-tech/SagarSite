<?php
/**
 * ============================================================
 *  MANAGE BACKUPS — Admin Panel
 *  Location: /admin/manage_backups.php
 * ============================================================
 *  Complete website backup/restore management interface.
 *  Features: Manual/Auto backup, Restore, Download, Delete
 * ============================================================
 */
$current_page = 'manage_backups.php';
include 'admin_header.php';
?>

<style>
/* ── Backup Manager Styles ─────────────────────────────────── */
.backup-hero {
    background: linear-gradient(135deg, #0d1b2a 0%, #1b2838 40%, #233b52 100%);
    border-radius: 20px;
    padding: 2.5rem;
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
    padding: 1.2rem;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease, background 0.3s ease;
}
.backup-stat-card:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,0.12);
}
.backup-stat-card .stat-value {
    font-size: 1.8rem;
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

/* ── Action Buttons ──────────────────────────────────── */
.backup-action-btn {
    border: none;
    border-radius: 14px;
    padding: 1.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    color: #fff;
    width: 100%;
}
.backup-action-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
.backup-action-btn .btn-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
}
.backup-action-btn .btn-title {
    font-weight: 700;
    font-size: 1rem;
    display: block;
}
.backup-action-btn .btn-desc {
    font-size: 0.75rem;
    opacity: 0.85;
    margin-top: 0.3rem;
    display: block;
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
.status-completed {
    background: #d4edda;
    color: #155724;
}
.status-failed {
    background: #f8d7da;
    color: #721c24;
}
.status-in_progress {
    background: #cce5ff;
    color: #004085;
}
.status-restored {
    background: #e2d9f3;
    color: #4a148c;
}
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

/* ── Restore Modal ────────────────────────────────────── */
.restore-warning-box {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    border-radius: 12px;
    padding: 1.2rem;
    border-left: 4px solid #e65100;
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

/* ── Pulse animation for in-progress ──────────────────── */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.pulse { animation: pulse 1.5s ease-in-out infinite; }

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
                        <i class="fas fa-shield-alt me-2"></i>Backup & Restore
                    </h3>
                    <p class="mb-0 opacity-75" style="font-size:0.92rem;">
                        Complete website backup management — protect your database, files, and configurations. Create, restore, and download backups safely.
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

    <!-- ════════ QUICK ACTION BUTTONS ════════ -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <button class="backup-action-btn btn-full" onclick="createBackup('full')" id="btnFullBackup">
                <span class="btn-icon"><i class="fas fa-database"></i> <i class="fas fa-plus" style="font-size:0.8rem;"></i></span>
                <span class="btn-title">Full Backup</span>
                <span class="btn-desc">Database + All Files</span>
            </button>
        </div>
        <div class="col-md-4">
            <button class="backup-action-btn btn-db" onclick="createBackup('db_only')" id="btnDbBackup">
                <span class="btn-icon"><i class="fas fa-server"></i></span>
                <span class="btn-title">Database Only</span>
                <span class="btn-desc">All tables & data</span>
            </button>
        </div>
        <div class="col-md-4">
            <button class="backup-action-btn btn-files" onclick="createBackup('files_only')" id="btnFilesBackup">
                <span class="btn-icon"><i class="fas fa-folder-open"></i></span>
                <span class="btn-title">Files Only</span>
                <span class="btn-desc">Uploads, Assets & Configs</span>
            </button>
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
                    <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Backup History</h5>
                    <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="loadBackups()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="backup-table-wrapper">
                        <table class="table backup-table mb-0">
                            <thead>
                                <tr>
                                    <th>Backup</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupTableBody">
                                <tr>
                                    <td colspan="6" class="empty-state">
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
                    <h5 class="fw-bold mb-3"><i class="fas fa-clock me-2 text-warning"></i>Auto Backup</h5>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="autoBackupEnabled" style="width:3rem;height:1.5rem;">
                        <label class="form-check-label fw-semibold ms-2" for="autoBackupEnabled">Enable Auto Backup</label>
                    </div>

                    <div id="autoBackupOptions">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Frequency</label>
                            <select class="form-select rounded-3" id="autoBackupFrequency">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Backup Type</label>
                            <select class="form-select rounded-3" id="autoBackupType">
                                <option value="full">Full (DB + Files)</option>
                                <option value="db_only">Database Only</option>
                                <option value="files_only">Files Only</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Max Backups to Keep</label>
                            <input type="number" class="form-control rounded-3" id="maxBackupsKeep" min="1" max="50" value="5">
                            <div class="form-text">Older backups will be auto-deleted.</div>
                        </div>

                        <button class="btn btn-primary w-100 rounded-pill btn-custom" onclick="saveAutoSettings()" id="btnSaveSettings">
                            <i class="fas fa-save me-2"></i>Save Settings
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
                            <strong>Database:</strong> All tables, data & structure
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-images text-success me-2"></i>
                            <strong>Uploads:</strong> Media, images & downloads
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-palette text-warning me-2"></i>
                            <strong>Assets:</strong> CSS, JS & images
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-cog text-danger me-2"></i>
                            <strong>Config:</strong> .env, .htaccess & settings
                        </li>
                    </ul>
                </div>
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
                            <strong>Warning:</strong> Restoring will overwrite current data. A safety backup will be created automatically before restore.
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
                <p class="text-muted small">This action cannot be undone. The backup file will be permanently removed.</p>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0">
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
let restoreModalInstance, deleteModalInstance, detailsModalInstance;

document.addEventListener('DOMContentLoaded', function() {
    restoreModalInstance = new mdb.Modal(document.getElementById('restoreModal'));
    deleteModalInstance = new mdb.Modal(document.getElementById('deleteModal'));
    detailsModalInstance = new mdb.Modal(document.getElementById('detailsModal'));
    
    loadBackups();
    loadAutoSettings();
});


// ═══════════════════════════════════════════════════════════
//  CREATE BACKUP
// ═══════════════════════════════════════════════════════════
function createBackup(type) {
    // Disable all action buttons
    document.querySelectorAll('.backup-action-btn').forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.6';
        b.style.pointerEvents = 'none';
    });

    // Show progress card
    const progressCard = document.getElementById('progressCard');
    progressCard.classList.add('active');
    
    const typeLabels = { full: 'Full', db_only: 'Database', files_only: 'Files' };
    document.getElementById('progressTitle').textContent = `Creating ${typeLabels[type]} Backup...`;
    document.getElementById('progressSubtext').textContent = 'This may take a few minutes for large sites. Please do not close this page.';

    // Animate progress ring (simulated since the backend is a single request)
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress = Math.min(progress + Math.random() * 8, 90);
        updateProgressRing(progress);
    }, 500);

    // Make AJAX request
    const formData = new FormData();
    formData.append('action', 'create_backup');
    formData.append('backup_type', type);
    formData.append('_csrf_token', CSRF_TOKEN);

    fetch('ajax_backup.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            clearInterval(progressInterval);
            
            if (data.success) {
                updateProgressRing(100);
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Backup Created!';
                document.getElementById('progressSubtext').innerHTML = `
                    <span class="text-success fw-bold">${data.backup.name}</span><br>
                    <span class="text-muted">Size: ${data.backup.size} | Tables: ${data.backup.tables} | Files: ${data.backup.files}</span>
                `;
                document.getElementById('progressRing').style.background = 
                    `conic-gradient(#14a44d 100%, #e9ecef 100%)`;
                
                // Reload table and stats
                loadBackups();
            } else {
                document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Backup Failed';
                document.getElementById('progressSubtext').innerHTML = `<span class="text-danger">${data.error || 'Unknown error occurred.'}</span>`;
                document.getElementById('progressRing').style.background = 
                    `conic-gradient(#dc4c64 100%, #e9ecef 100%)`;
                document.getElementById('progressPercent').innerHTML = '<i class="fas fa-times text-danger"></i>';
            }
        })
        .catch(err => {
            clearInterval(progressInterval);
            document.getElementById('progressTitle').innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Connection Error';
            document.getElementById('progressSubtext').textContent = err.message;
        })
        .finally(() => {
            // Re-enable buttons after 2 seconds
            setTimeout(() => {
                document.querySelectorAll('.backup-action-btn').forEach(b => {
                    b.disabled = false;
                    b.style.opacity = '1';
                    b.style.pointerEvents = 'auto';
                });
                // Auto-hide progress after 5 seconds
                setTimeout(() => {
                    progressCard.classList.remove('active');
                    updateProgressRing(0);
                }, 5000);
            }, 2000);
        });
}

function updateProgressRing(pct) {
    const ring = document.getElementById('progressRing');
    const percentEl = document.getElementById('progressPercent');
    ring.style.setProperty('--progress', pct + '%');
    ring.style.background = `conic-gradient(#14a44d ${pct}%, #e9ecef ${pct}%)`;
    percentEl.textContent = Math.round(pct) + '%';
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
                    '<tr><td colspan="6" class="text-center text-danger py-4">Error loading backups.</td></tr>';
                return;
            }

            const tbody = document.getElementById('backupTableBody');
            
            if (data.backups.length === 0) {
                tbody.innerHTML = `
                    <tr><td colspan="6" class="empty-state">
                        <i class="fas fa-cloud-upload-alt d-block"></i>
                        <h6 class="mt-2 mb-1">No Backups Yet</h6>
                        <p class="mb-0 small">Click one of the buttons above to create your first backup.</p>
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
                        'files_only': 'fa-folder'
                    }[b.backup_type] || 'fa-file';

                    const typeLabel = {
                        'full': 'Full',
                        'db_only': 'DB Only',
                        'files_only': 'Files'
                    }[b.backup_type] || b.backup_type;

                    const triggerBadge = b.trigger_type === 'auto' 
                        ? '<span class="badge bg-info ms-1" style="font-size:0.6rem;">AUTO</span>' : '';

                    html += `
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:0.85rem;">${escHtml(b.backup_name)}${triggerBadge}</div>
                            <div class="text-muted" style="font-size:0.72rem;">
                                ${b.created_by_name ? 'by ' + escHtml(b.created_by_name) : ''}
                                ${b.db_tables_count > 0 ? ' · ' + b.db_tables_count + ' tables' : ''}
                                ${b.files_count > 0 ? ' · ' + b.files_count + ' files' : ''}
                            </div>
                        </td>
                        <td><span class="type-badge type-${b.backup_type}"><i class="fas ${typeIcon}"></i> ${typeLabel}</span></td>
                        <td>
                            <span class="fw-semibold">${b.file_size_formatted}</span>
                            ${!b.file_exists && b.status === 'completed' ? '<br><span class="text-danger" style="font-size:0.7rem;"><i class="fas fa-exclamation-triangle"></i> File missing</span>' : ''}
                        </td>
                        <td><span class="status-badge status-${b.status}"><i class="fas ${statusIcon}"></i> ${capitalize(b.status)}</span></td>
                        <td><span style="font-size:0.82rem;">${b.created_at_formatted}</span></td>
                        <td>
                            <div class="action-btn-group d-flex gap-1 justify-content-center flex-wrap">
                                ${b.file_exists && b.status === 'completed' ? `
                                    <a href="ajax_backup.php?action=download_backup&id=${b.id}" class="btn btn-sm btn-outline-primary" title="Download" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning" title="Restore" onclick="showRestore(${b.id}, '${escJs(b.backup_name)}', '${escJs(b.created_at_formatted)}', '${b.backup_type}')">
                                        <i class="fas fa-undo-alt"></i>
                                    </button>
                                ` : ''}
                                ${b.status === 'restored' && b.file_exists ? `
                                    <a href="ajax_backup.php?action=download_backup&id=${b.id}" class="btn btn-sm btn-outline-primary" title="Download" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
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

            // Pagination
            renderPagination(data.page, data.pages, data.total);
        })
        .catch(err => {
            document.getElementById('backupTableBody').innerHTML = 
                `<tr><td colspan="6" class="text-center text-danger py-4">Failed to load: ${err.message}</td></tr>`;
        });
}


// ═══════════════════════════════════════════════════════════
//  RESTORE
// ═══════════════════════════════════════════════════════════
function showRestore(id, name, date, type) {
    restoreBackupId = id;
    document.getElementById('restoreBackupName').textContent = name;
    document.getElementById('restoreBackupDate').textContent = date;
    
    // Auto-set checkboxes based on backup type
    document.getElementById('restoreDbCheck').checked = (type === 'full' || type === 'db_only');
    document.getElementById('restoreFilesCheck').checked = (type === 'full' || type === 'files_only');
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
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
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
                document.getElementById('detailsContent').innerHTML = `
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted fw-semibold" style="width:40%;">Name</td><td class="fw-bold">${escHtml(b.backup_name)}</td></tr>
                        <tr><td class="text-muted fw-semibold">Type</td><td><span class="type-badge type-${b.backup_type}">${capitalize(b.backup_type.replace('_',' '))}</span></td></tr>
                        <tr><td class="text-muted fw-semibold">Trigger</td><td>${capitalize(b.trigger_type)}</td></tr>
                        <tr><td class="text-muted fw-semibold">Status</td><td><span class="status-badge status-${b.status}">${capitalize(b.status)}</span></td></tr>
                        <tr><td class="text-muted fw-semibold">Size</td><td>${b.file_size_formatted}</td></tr>
                        <tr><td class="text-muted fw-semibold">DB Tables</td><td>${b.db_tables_count || '—'}</td></tr>
                        <tr><td class="text-muted fw-semibold">Files</td><td>${b.files_count || '—'}</td></tr>
                        <tr><td class="text-muted fw-semibold">Created</td><td>${b.created_at_formatted}</td></tr>
                        <tr><td class="text-muted fw-semibold">Created By</td><td>${escHtml(b.created_by_name || '—')}</td></tr>
                        <tr><td class="text-muted fw-semibold">File Exists</td><td>${b.file_exists ? '<span class="text-success"><i class="fas fa-check-circle"></i> Yes</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> No</span>'}</td></tr>
                        ${b.notes ? `<tr><td class="text-muted fw-semibold">Notes</td><td class="small">${escHtml(b.notes)}</td></tr>` : ''}
                    </table>
                `;
            } else {
                document.getElementById('detailsContent').innerHTML = `<p class="text-danger">${data.error}</p>`;
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
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Settings';
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
    // Create a temporary alert
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
