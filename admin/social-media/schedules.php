<?php
$current_page = 'social-media/schedules.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT start_date FROM sm_schedules LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}

$csrfToken = csrf_token();

// Fetch existing schedules
$stmtSchedules = $pdo->query("SELECT * FROM sm_schedules ORDER BY id DESC");
$schedules = $stmtSchedules->fetchAll(PDO::FETCH_ASSOC);

// Fetch Templates
$stmtTemplates = $pdo->query("SELECT id, name, is_default FROM sm_templates WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
$templates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);

// Fetch Connected Accounts
$stmtAccounts = $pdo->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1 AND access_token_encrypted IS NOT NULL AND access_token_encrypted != ''");
$activeAccounts = $stmtAccounts->fetchAll(PDO::FETCH_ASSOC);
$connectedMap = [];
foreach ($activeAccounts as $acc) {
    $connectedMap[strtolower($acc['platform'])] = $acc;
}

// Fetch Categories
$categories = [];
try {
    $stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmtCat = $pdo->query("SELECT DISTINCT category as name FROM products WHERE category IS NOT NULL AND category != ''");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Brands
$brands = [];
try {
    $stmtBrand = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC");
    $brands = $stmtBrand->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$platformIcons = [
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'name' => 'Facebook'],
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'name' => 'Instagram'],
    'twitter' => ['icon' => 'fab fa-x-twitter', 'color' => '#000000', 'name' => 'X (Twitter)'],
    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'name' => 'LinkedIn'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088CC', 'name' => 'Telegram'],
    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#E60023', 'name' => 'Pinterest']
];

$scheduleTypes = [
    'every_5min' => 'Every 5 Minutes',
    'every_15min' => 'Every 15 Minutes',
    'every_30min' => 'Every 30 Minutes',
    'every_1hr' => 'Every 1 Hour',
    'every_2hr' => 'Every 2 Hours',
    'every_6hr' => 'Every 6 Hours',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'custom' => 'Custom Interval'
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold m-0">Posting Schedules</h2>
            <p class="text-muted small m-0">Create and manage automated posting intervals across social media platforms.</p>
        </div>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnOpenCreateModal">
            <i class="fas fa-plus me-2"></i> Create Schedule
        </button>
    </div>

    <div id="pageAlert"></div>

    <?php if (empty($schedules)): ?>
        <div class="card shadow border-0 rounded-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3 d-block"></i>
                <h4 class="fw-bold text-secondary">No schedules defined yet</h4>
                <p class="text-muted mb-4">Create a schedule to define automated posting intervals for your products.</p>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btnOpenCreateModalEmpty">
                    <i class="fas fa-plus me-2"></i> Create Your First Schedule
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($schedules as $sched): 
                $pIds = json_decode($sched['platform_ids'] ?? '[]', true) ?: [];
                $typeLabel = $scheduleTypes[$sched['schedule_type']] ?? ucfirst($sched['schedule_type']);
                $isActive = (int)$sched['is_active'] === 1;
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow border-0 rounded-4 h-100 sm-card">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-dark m-0"><?php echo htmlspecialchars($sched['name']); ?></h5>
                                    <span class="badge <?php echo $isActive ? 'bg-success text-white' : 'bg-secondary text-white'; ?> rounded-pill px-3 py-2">
                                        <?php echo $isActive ? 'Active' : 'Paused'; ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <span class="text-muted small d-block mb-1">Frequency:</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold">
                                        <i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($typeLabel); ?>
                                        <?php if ($sched['schedule_type'] === 'custom' && !empty($sched['interval_minutes'])): ?>
                                            (<?php echo $sched['interval_minutes']; ?> min)
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <span class="text-muted small d-block mb-2">Target Platforms:</span>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (empty($pIds)): ?>
                                            <span class="text-muted small fst-italic">All Platforms</span>
                                        <?php else: ?>
                                            <?php foreach ($pIds as $pKey): 
                                                $pMeta = $platformIcons[strtolower($pKey)] ?? ['icon' => 'fas fa-share-alt', 'color' => '#6c757d', 'name' => ucfirst($pKey)];
                                            ?>
                                                <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small">
                                                    <i class="<?php echo $pMeta['icon']; ?> me-1" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                                    <?php echo htmlspecialchars($pMeta['name']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="border-top pt-2 mt-3">
                                    <div class="text-muted extra-small mb-1">
                                        <i class="fas fa-calendar-alt text-info me-1"></i> <strong>Start Time:</strong> 
                                        <?php 
                                            $sDT = (!empty($sched['start_date']) ? $sched['start_date'] : date('Y-m-d')) . ' ' . (!empty($sched['start_time']) ? $sched['start_time'] : '00:00:00');
                                            echo date('M d, Y h:i A', strtotime($sDT)); 
                                        ?>
                                    </div>
                                    <div class="text-muted extra-small mb-1">
                                        <i class="fas fa-history text-secondary me-1"></i> <strong>Last Run:</strong> 
                                        <?php echo !empty($sched['last_run_at']) ? date('M d, Y h:i A', strtotime($sched['last_run_at'])) : 'Never'; ?>
                                    </div>
                                    <div class="text-muted extra-small">
                                        <i class="fas fa-hourglass-half text-primary me-1"></i> <strong>Next Run:</strong> 
                                        <?php echo !empty($sched['next_run_at']) ? date('M d, Y h:i A', strtotime($sched['next_run_at'])) : ($isActive ? 'Due Now / On Cron' : 'Paused'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-toggle-status" 
                                            data-id="<?php echo $sched['id']; ?>">
                                        <i class="fas <?php echo $isActive ? 'fa-pause text-warning' : 'fa-play text-success'; ?> me-1"></i>
                                        <?php echo $isActive ? 'Pause' : 'Activate'; ?>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success rounded-pill btn-run-now" 
                                            data-id="<?php echo $sched['id']; ?>" title="Execute Schedule Now">
                                        <i class="fas fa-bolt me-1"></i> Run Now
                                    </button>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-schedule" 
                                            title="Edit Schedule"
                                            data-schedule='<?php echo htmlspecialchars(json_encode($sched), ENT_QUOTES); ?>'>
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-schedule" 
                                            title="Delete Schedule"
                                            data-id="<?php echo $sched['id']; ?>">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Create / Edit Schedule -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <form id="scheduleForm">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="scheduleId" value="">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="scheduleModalLabel">Create Posting Schedule</h5>
                    <button type="button" class="btn-close btn-close-modal" data-bs-dismiss="modal" data-mdb-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="schedName" class="form-label fw-bold small text-muted">Schedule Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="schedName" name="name" 
                                   placeholder="e.g. Daily Morning Posts" required>
                        </div>

                        <div class="col-md-6">
                            <label for="schedType" class="form-label fw-bold small text-muted">Posting Frequency <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" id="schedType" name="schedule_type" required>
                                <?php foreach ($scheduleTypes as $typeKey => $typeVal): ?>
                                    <option value="<?php echo $typeKey; ?>"><?php echo htmlspecialchars($typeVal); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="schedStartMode" class="form-label fw-bold small text-muted">Posting Start <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" id="schedStartMode" name="start_mode" required>
                                <option value="once_daily">Once a Daily</option>
                                <option value="once_weekly">Once a Weekly</option>
                                <option value="once_monthly">Once a Monthly</option>
                                <option value="custom">Custom Posting</option>
                            </select>
                            <div class="mt-2" id="customStartDateGroup" style="display: none;">
                                <label for="schedStartDate" class="form-label extra-small text-muted mb-1">Select Custom Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control rounded-3" id="schedStartDate" name="start_date">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="schedStartTime" class="form-label fw-bold small text-muted">Posting Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control rounded-3" id="schedStartTime" name="start_time" required>
                        </div>

                        <div class="col-md-12" id="customIntervalGroup" style="display: none;">
                            <label for="schedInterval" class="form-label fw-bold small text-muted">Interval (in Minutes)</label>
                            <input type="number" class="form-control rounded-3" id="schedInterval" name="interval_minutes" value="60" min="5" step="5">
                        </div>

                        <div class="col-md-6">
                            <label for="schedTemplate" class="form-label fw-bold small text-muted">Caption Template</label>
                            <select class="form-select rounded-3" id="schedTemplate" name="template_id">
                                <option value="">Default Promotion Template</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo $tpl['id']; ?>"><?php echo htmlspecialchars($tpl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="schedCta" class="form-label fw-bold small text-muted">Call to Action (CTA)</label>
                            <select class="form-select rounded-3" id="schedCta" name="cta">
                                <option value="Shop Now 🛒">Shop Now 🛒</option>
                                <option value="Buy Now 🛍️">Buy Now 🛍️</option>
                                <option value="Order Today 📦">Order Today 📦</option>
                                <option value="Limited Time Offer 🔥">Limited Time Offer 🔥</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label for="schedHashtags" class="form-label fw-bold small text-muted">Custom Hashtags</label>
                            <input type="text" class="form-control rounded-3" id="schedHashtags" name="hashtags" 
                                   placeholder="#SagarStarters #Sale #Shopping #Trending">
                        </div>

                        <div class="col-md-6">
                            <label for="schedFilterType" class="form-label fw-bold small text-muted">Product Scope</label>
                            <select class="form-select rounded-3" id="schedFilterType" name="filter_type">
                                <option value="all">All Products</option>
                                <option value="category">Category-wise</option>
                                <option value="brand">Brand-wise</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="filterValueGroup" style="display: none;">
                            <label for="schedFilterValue" class="form-label fw-bold small text-muted" id="filterValueLabel">Filter Value</label>
                            <select class="form-select rounded-3" id="schedFilterValue" name="filter_value">
                                <option value="">-- Select --</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small text-muted m-0">Target Platforms <span class="text-danger">*</span></label>
                                <a href="accounts.php" class="extra-small text-decoration-none"><i class="fas fa-plug me-1"></i>Manage Accounts</a>
                            </div>
                            <?php if (empty($connectedMap)): ?>
                                <div class="alert alert-warning py-2 px-3 small rounded-3 mb-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i> No social media accounts connected yet. <a href="accounts.php" class="alert-link fw-bold">Connect Accounts</a> to enable posting.
                                </div>
                            <?php endif; ?>
                            <div class="row g-2">
                                <?php foreach ($platformIcons as $pKey => $pMeta): 
                                    $isConnected = isset($connectedMap[$pKey]);
                                    $accInfo = $isConnected ? $connectedMap[$pKey] : null;
                                ?>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check border rounded-3 p-2 px-3 h-100 <?php echo $isConnected ? 'bg-white shadow-sm' : 'bg-light opacity-75'; ?>">
                                            <input class="form-check-input platform-chk" type="checkbox" 
                                                   name="platforms[]" value="<?php echo $pKey; ?>" id="chk_<?php echo $pKey; ?>"
                                                   data-connected="<?php echo $isConnected ? '1' : '0'; ?>"
                                                   <?php echo $isConnected ? '' : 'disabled'; ?>>
                                            <label class="form-check-label fw-semibold small <?php echo $isConnected ? 'cursor-pointer' : 'text-muted'; ?>" for="chk_<?php echo $pKey; ?>">
                                                <i class="<?php echo $pMeta['icon']; ?> me-1" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                                <?php echo htmlspecialchars($pMeta['name']); ?>
                                                <?php if ($isConnected): ?>
                                                    <span class="d-block extra-small text-success fw-normal mt-1 text-truncate" title="<?php echo htmlspecialchars($accInfo['account_name'] ?? 'Connected'); ?>">
                                                        <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($accInfo['account_name'] ?? 'Connected'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="d-block extra-small text-muted fw-normal mt-1">
                                                        <i class="fas fa-times-circle me-1"></i>Not Connected
                                                    </span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="schedActive" name="is_active" value="1" checked>
                                <label class="form-check-label fw-bold small" for="schedActive">Enable / Activate Schedule Immediately</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 btn-close-modal" data-bs-dismiss="modal" data-mdb-dismiss="modal" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnSaveSchedule">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.extra-small { font-size: 0.78rem; }
.cursor-pointer { cursor: pointer; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleForm = document.getElementById('scheduleForm');
    const schedTypeSelect = document.getElementById('schedType');
    const customIntervalGroup = document.getElementById('customIntervalGroup');
    const schedFilterType = document.getElementById('schedFilterType');
    const filterValueGroup = document.getElementById('filterValueGroup');
    const filterValueSelect = document.getElementById('schedFilterValue');
    const filterValueLabel = document.getElementById('filterValueLabel');
    const modalEl = document.getElementById('scheduleModal');

    const categoriesData = <?php echo json_encode($categories); ?>;
    const brandsData = <?php echo json_encode($brands); ?>;

    function getModalInstance() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        } else if (typeof mdb !== 'undefined' && mdb.Modal) {
            return mdb.Modal.getInstance(modalEl) || new mdb.Modal(modalEl);
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            return { show: () => $(modalEl).modal('show'), hide: () => $(modalEl).modal('hide') };
        }
        return null;
    }

    function showScheduleModal() {
        const inst = getModalInstance();
        if (inst && typeof inst.show === 'function') inst.show();
        else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function hideScheduleModal() {
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            const inst = mdb.Modal.getInstance(modalEl);
            if (inst && typeof inst.hide === 'function') inst.hide();
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(modalEl);
            if (inst && typeof inst.hide === 'function') inst.hide();
        }
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $(modalEl).modal('hide');
        }
        // Direct DOM fallback
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }

    document.querySelectorAll('.btn-close-modal, [data-bs-dismiss="modal"], [data-mdb-dismiss="modal"], [data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            hideScheduleModal();
        });
    });

    function checkIntervalVisibility() {
        if (schedTypeSelect && schedTypeSelect.value === 'custom') {
            customIntervalGroup.style.display = 'block';
        } else if (customIntervalGroup) {
            customIntervalGroup.style.display = 'none';
        }
    }
    if (schedTypeSelect) {
        schedTypeSelect.addEventListener('change', checkIntervalVisibility);
    }

    const schedStartMode = document.getElementById('schedStartMode');
    const customStartDateGroup = document.getElementById('customStartDateGroup');
    const schedStartDate = document.getElementById('schedStartDate');

    function checkStartModeVisibility() {
        const nowObj = new Date();
        const todayStr = nowObj.getFullYear() + '-' + String(nowObj.getMonth() + 1).padStart(2, '0') + '-' + String(nowObj.getDate()).padStart(2, '0');
        if (schedStartMode && schedStartMode.value === 'custom') {
            if (customStartDateGroup) customStartDateGroup.style.display = 'block';
            if (schedStartDate) {
                if (!schedStartDate.value) schedStartDate.value = todayStr;
            }
        } else {
            if (customStartDateGroup) customStartDateGroup.style.display = 'none';
            if (schedStartDate) {
                schedStartDate.value = todayStr;
            }
        }
    }
    if (schedStartMode) {
        schedStartMode.addEventListener('change', checkStartModeVisibility);
    }

    function updateFilterValueOptions(selectedVal) {
        filterValueSelect.innerHTML = '<option value="">-- Select --</option>';
        const type = schedFilterType.value;

        if (type === 'category') {
            filterValueLabel.textContent = 'Select Category';
            filterValueGroup.style.display = 'block';
            categoriesData.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id || c.name;
                opt.textContent = c.name;
                if (selectedVal && String(opt.value) === String(selectedVal)) opt.selected = true;
                filterValueSelect.appendChild(opt);
            });
        } else if (type === 'brand') {
            filterValueLabel.textContent = 'Select Brand';
            filterValueGroup.style.display = 'block';
            brandsData.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b;
                opt.textContent = b;
                if (selectedVal && String(opt.value) === String(selectedVal)) opt.selected = true;
                filterValueSelect.appendChild(opt);
            });
        } else {
            filterValueGroup.style.display = 'none';
        }
    }

    if (schedFilterType) {
        schedFilterType.addEventListener('change', () => updateFilterValueOptions());
    }

    // Open Modal Create
    function openCreateModal() {
        if (scheduleForm) scheduleForm.reset();
        document.getElementById('scheduleId').value = '';
        document.getElementById('scheduleModalLabel').textContent = 'Create Posting Schedule';
        
        document.querySelectorAll('.platform-chk').forEach(c => {
            if (c.dataset.connected === '1') {
                c.disabled = false;
                c.checked = true;
            } else {
                c.disabled = true;
                c.checked = false;
            }
        });
        document.getElementById('schedActive').checked = true;

        const nowObj = new Date();
        const todayStr = nowObj.getFullYear() + '-' + String(nowObj.getMonth() + 1).padStart(2, '0') + '-' + String(nowObj.getDate()).padStart(2, '0');
        const timeStr = String(nowObj.getHours()).padStart(2, '0') + ':' + String(nowObj.getMinutes()).padStart(2, '0');
        
        if (schedStartMode) schedStartMode.value = 'once_daily';
        if (schedStartDate) schedStartDate.value = todayStr;
        document.getElementById('schedStartTime').value = timeStr;

        checkStartModeVisibility();
        checkIntervalVisibility();
        updateFilterValueOptions();
        showScheduleModal();
    }

    const btnOpenCreate = document.getElementById('btnOpenCreateModal');
    if (btnOpenCreate) btnOpenCreate.addEventListener('click', openCreateModal);

    const btnOpenCreateEmpty = document.getElementById('btnOpenCreateModalEmpty');
    if (btnOpenCreateEmpty) btnOpenCreateEmpty.addEventListener('click', openCreateModal);

    // Edit Schedule
    document.querySelectorAll('.btn-edit-schedule').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.dataset.schedule);
                document.getElementById('scheduleId').value = data.id;
                document.getElementById('schedName').value = data.name;
                document.getElementById('schedType').value = data.schedule_type;
                document.getElementById('schedInterval').value = data.interval_minutes || 60;
                document.getElementById('schedTemplate').value = data.template_id || '';
                document.getElementById('schedCta').value = data.cta || 'Shop Now 🛒';
                document.getElementById('schedHashtags').value = data.hashtags || '';
                document.getElementById('schedFilterType').value = data.filter_type || 'all';
                document.getElementById('schedActive').checked = parseInt(data.is_active) === 1;

                const nowObj = new Date();
                const todayStr = nowObj.getFullYear() + '-' + String(nowObj.getMonth() + 1).padStart(2, '0') + '-' + String(nowObj.getDate()).padStart(2, '0');
                const timeStr = String(nowObj.getHours()).padStart(2, '0') + ':' + String(nowObj.getMinutes()).padStart(2, '0');

                let sMode = data.start_mode;
                if (!sMode || sMode === 'once_day') sMode = (data.start_date && data.start_date !== todayStr ? 'custom' : 'once_daily');
                if (schedStartMode) schedStartMode.value = sMode;
                if (schedStartDate) schedStartDate.value = data.start_date || todayStr;
                document.getElementById('schedStartTime').value = data.start_time ? data.start_time.substring(0, 5) : timeStr;

                checkStartModeVisibility();
                updateFilterValueOptions(data.filter_value);

                let pIds = [];
                if (typeof data.platform_ids === 'string') {
                    try { pIds = JSON.parse(data.platform_ids); } catch(e) { pIds = []; }
                } else if (Array.isArray(data.platform_ids)) {
                    pIds = data.platform_ids;
                }

                document.querySelectorAll('.platform-chk').forEach(chk => {
                    if (chk.dataset.connected === '1') {
                        chk.disabled = false;
                        chk.checked = pIds.includes(chk.value);
                    } else {
                        chk.disabled = true;
                        chk.checked = false;
                    }
                });

                document.getElementById('scheduleModalLabel').textContent = 'Edit Schedule';
                checkIntervalVisibility();
                showScheduleModal();
            } catch (err) {
                console.error('Error opening edit modal:', err);
            }
        });
    });

    // Save Schedule Form Submit
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const checkedPlatforms = Array.from(document.querySelectorAll('.platform-chk:checked'));
            if (checkedPlatforms.length === 0) {
                alert('Please select at least 1 connected social media platform for automated posting.');
                return;
            }

            const btnSave = document.getElementById('btnSaveSchedule');
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const formData = new FormData(this);

            fetch('ajax/ajax_schedule_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnSave.disabled = false;
                btnSave.innerHTML = 'Save Schedule';
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to save schedule: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                btnSave.disabled = false;
                btnSave.innerHTML = 'Save Schedule';
                alert('Error saving schedule: ' + err.message);
            });
        });
    }

    // Run Now Button
    document.querySelectorAll('.btn-run-now').forEach(btn => {
        btn.addEventListener('click', function() {
            const schedId = this.dataset.id;
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const formData = new FormData();
            formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('action', 'run_now');
            formData.append('id', schedId);

            fetch('ajax/ajax_schedule_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.disabled = false;
                this.innerHTML = originalHtml;
                const pageAlert = document.getElementById('pageAlert');
                if (data.success) {
                    pageAlert.innerHTML = `<div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <i class="fas fa-check-circle me-2"></i> ${data.message}
                        <a href="queue.php" class="fw-bold ms-2 text-success text-decoration-underline">View Queue</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    pageAlert.innerHTML = `<div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> ${data.error || 'Execution failed.'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                }
            })
            .catch(err => {
                this.disabled = false;
                this.innerHTML = originalHtml;
                alert('Error executing schedule: ' + err.message);
            });
        });
    });

    // Toggle Active Status
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('action', 'toggle_status');
            formData.append('id', this.dataset.id);

            fetch('ajax/ajax_schedule_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Status toggle failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error toggling status: ' + err.message));
        });
    });

    // Delete Schedule
    document.querySelectorAll('.btn-delete-schedule').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const formData = new FormData();
                formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
                formData.append('action', 'delete');
                formData.append('id', this.dataset.id);

                fetch('ajax/ajax_schedule_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Delete schedule failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error deleting schedule: ' + err.message));
            }
        });
    });
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>