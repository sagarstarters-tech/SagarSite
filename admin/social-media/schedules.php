<?php
$current_page = 'social-media/schedules.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT 1 FROM sm_schedules LIMIT 1");
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

$platformIcons = [
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'name' => 'Facebook'],
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'name' => 'Instagram'],
    'twitter' => ['icon' => 'fab fa-x-twitter', 'color' => '#000000', 'name' => 'X (Twitter)'],
    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'name' => 'LinkedIn'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088CC', 'name' => 'Telegram'],
    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#E60023', 'name' => 'Pinterest']
];

$scheduleTypes = [
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
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnOpenCreateModal" data-mdb-toggle="modal" data-mdb-target="#scheduleModal" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            <i class="fas fa-plus me-2"></i> Create Schedule
        </button>
    </div>

    <?php if (empty($schedules)): ?>
        <div class="card shadow border-0 rounded-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3 d-block"></i>
                <h4 class="fw-bold text-secondary">No schedules defined yet</h4>
                <p class="text-muted mb-4">Create a schedule to define automated posting intervals for your products.</p>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btnOpenCreateModalEmpty" data-mdb-toggle="modal" data-mdb-target="#scheduleModal" data-bs-toggle="modal" data-bs-target="#scheduleModal">
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

                                <div class="mb-4">
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
                            </div>

                            <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-toggle-status" 
                                        data-id="<?php echo $sched['id']; ?>">
                                    <i class="fas <?php echo $isActive ? 'fa-pause text-warning' : 'fa-play text-success'; ?> me-1"></i>
                                    <?php echo $isActive ? 'Pause' : 'Activate'; ?>
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-schedule" 
                                            data-schedule='<?php echo htmlspecialchars(json_encode($sched), ENT_QUOTES); ?>'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-schedule" 
                                            data-id="<?php echo $sched['id']; ?>">
                                        <i class="fas fa-trash-alt"></i>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form id="scheduleForm">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="scheduleId" value="">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="scheduleModalLabel">Create Posting Schedule</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="schedName" class="form-label fw-bold small text-muted">Schedule Name</label>
                        <input type="text" class="form-control rounded-3" id="schedName" name="name" 
                               placeholder="e.g. Daily Morning Posts" required>
                    </div>

                    <div class="mb-3">
                        <label for="schedType" class="form-label fw-bold small text-muted">Posting Frequency</label>
                        <select class="form-select rounded-3" id="schedType" name="schedule_type" required>
                            <?php foreach ($scheduleTypes as $typeKey => $typeVal): ?>
                                <option value="<?php echo $typeKey; ?>"><?php echo htmlspecialchars($typeVal); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="customIntervalGroup" style="display: none;">
                        <label for="schedInterval" class="form-label fw-bold small text-muted">Interval (in Minutes)</label>
                        <input type="number" class="form-control rounded-3" id="schedInterval" name="interval_minutes" value="60" min="5" step="5">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted d-block">Target Platforms</label>
                        <div class="row g-2">
                            <?php foreach ($platformIcons as $pKey => $pMeta): ?>
                                <div class="col-6">
                                    <div class="form-check border rounded-3 p-2 px-3">
                                        <input class="form-check-input platform-chk" type="checkbox" 
                                               name="platforms[]" value="<?php echo $pKey; ?>" id="chk_<?php echo $pKey; ?>">
                                        <label class="form-check-label cursor-pointer fw-semibold small" for="chk_<?php echo $pKey; ?>">
                                            <i class="<?php echo $pMeta['icon']; ?> me-1" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                            <?php echo htmlspecialchars($pMeta['name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="schedActive" name="is_active" value="1" checked>
                        <label class="form-check-label fw-bold small" for="schedActive">Enable / Activate Schedule Immediately</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnSaveSchedule">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleForm = document.getElementById('scheduleForm');
    const schedTypeSelect = document.getElementById('schedType');
    const customIntervalGroup = document.getElementById('customIntervalGroup');
    const modalEl = document.getElementById('scheduleModal');

    function showScheduleModal() {
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            const inst = mdb.Modal.getInstance(modalEl) || new mdb.Modal(modalEl);
            inst.show();
        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            inst.show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $(modalEl).modal('show');
        }
    }

    // Toggle custom interval input visibility
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

    // Open Modal Create
    function openCreateModal() {
        if (scheduleForm) scheduleForm.reset();
        const idField = document.getElementById('scheduleId');
        if (idField) idField.value = '';
        const titleField = document.getElementById('scheduleModalLabel');
        if (titleField) titleField.textContent = 'Create Posting Schedule';
        document.querySelectorAll('.platform-chk').forEach(c => c.checked = true);
        const activeField = document.getElementById('schedActive');
        if (activeField) activeField.checked = true;
        checkIntervalVisibility();
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
                document.getElementById('schedActive').checked = parseInt(data.is_active) === 1;

                const pIds = JSON.parse(data.platform_ids || '[]');
                document.querySelectorAll('.platform-chk').forEach(chk => {
                    chk.checked = pIds.includes(chk.value);
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
            const formData = new FormData(this);

            fetch('ajax/ajax_schedule_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to save schedule: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error saving schedule: ' + err.message));
        });
    }

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