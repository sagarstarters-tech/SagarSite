<?php
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="row g-4 mt-1">
        <!-- Existing Card: Refresh & Optimize System -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-broom me-2"></i>Refresh & Optimize System</h4>
                    <p class="text-muted small">Manually clear cache, remove old sessions, and optimize database tables.</p>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div id="maintenance-init">
                        <div class="alert alert-info border-0 shadow-sm rounded-4 py-3">
                            <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>What does this do?</h6>
                            <ul class="mb-0 small">
                                <li>Clears application session files older than 24 hours.</li>
                                <li>Optimizes all database tables to reclaim unused space.</li>
                                <li>Optionally clears system logs to reduce database size.</li>
                                <li>Regenerates internal configuration cache.</li>
                            </ul>
                        </div>

                        <div class="form-check form-switch mb-4 fs-6">
                            <input class="form-check-input" type="checkbox" role="switch" id="clearLogsCheck">
                            <label class="form-check-label fw-bold" for="clearLogsCheck">Also Clear Audit Logs (Email & WhatsApp Logs)</label>
                            <div class="form-text">Warning: This will permanently delete your communication history.</div>
                        </div>

                        <button id="startMaintenance" class="btn btn-primary btn-lg btn-custom w-100 shadow-sm rounded-pill py-3">
                            <i class="fas fa-rocket me-2"></i>Run System Optimize Now
                        </button>
                    </div>

                    <div id="maintenance-progress" style="display: none;">
                        <h5 class="text-center mb-4 fw-bold">Optimizing System...</h5>
                        <div class="progress rounded-pill mb-3" style="height: 25px;">
                            <div id="maintenance-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p id="status-text" class="text-center text-muted small">Initializing...</p>
                    </div>

                    <div id="maintenance-report" style="display: none;">
                        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 text-center mb-4">
                            <h5 class="fw-bold mb-0"><i class="fas fa-check-circle me-2"></i>System Optimization Successful!</h5>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-md-6 border-end">
                                <h3 id="sessions-report" class="fw-bold text-primary">0</h3>
                                <p class="text-muted small text-uppercase fw-bold m-0">Old Sessions Cleared</p>
                            </div>
                            <div class="col-md-6">
                                <h3 id="tables-report" class="fw-bold text-primary">0</h3>
                                <p class="text-muted small text-uppercase fw-bold m-0">Tables Optimized</p>
                            </div>
                        </div>

                        <div class="card bg-light border-0 rounded-4">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-2 small text-uppercase">detailed report</h6>
                                <div id="report-details" style="max-height: 200px; overflow-y: auto;" class="small font-monospace">
                                </div>
                            </div>
                        </div>

                        <button onclick="location.reload()" class="btn btn-outline-primary rounded-pill w-100 mt-4 py-2 fw-bold italic">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Card: Boost Website Speed -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold mb-0 text-success"><i class="fas fa-bolt me-2 text-warning"></i>Boost Website Speed</h4>
                    <p class="text-muted small">Accelerate website performance safely with instant cache priming and index query optimization.</p>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div id="speedboost-init">
                        <div class="alert alert-success bg-success bg-opacity-10 border-0 shadow-sm rounded-4 py-3 text-dark">
                            <h6 class="fw-bold text-success"><i class="fas fa-shield-alt me-2"></i>Safe Speed Accelerator</h6>
                            <ul class="mb-0 small">
                                <li>Re-analyzes database table indexes (<code>ANALYZE TABLE</code>) for faster SQL query execution without data modification.</li>
                                <li>Primes configuration cache & updates static asset cache versions for instant browser loading.</li>
                                <li>Clears PHP OPcache bytecode cache for maximum PHP script performance.</li>
                                <li><strong class="text-success">Zero Deletion:</strong> No orders, products, sessions, or logs are deleted.</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <div class="text-muted small"><i class="fas fa-info-circle me-1"></i>Safe to run anytime during live site operation.</div>
                        </div>

                        <button id="startSpeedBoost" class="btn btn-success btn-lg btn-custom w-100 shadow-sm rounded-pill py-3 fw-bold">
                            <i class="fas fa-tachometer-alt me-2"></i>Boost Website Speed Now
                        </button>
                    </div>

                    <div id="speedboost-progress" style="display: none;">
                        <h5 class="text-center mb-4 fw-bold text-success">Boosting Website Speed...</h5>
                        <div class="progress rounded-pill mb-3" style="height: 25px;">
                            <div id="speedboost-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p id="speedboost-status-text" class="text-center text-muted small">Initializing Speed Accelerator...</p>
                    </div>

                    <div id="speedboost-report" style="display: none;">
                        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 text-center mb-4">
                            <h5 class="fw-bold mb-0"><i class="fas fa-rocket me-2"></i>Website Speed Boosted Successfully!</h5>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-md-4 border-end">
                                <h3 id="speed-tables-report" class="fw-bold text-success">0</h3>
                                <p class="text-muted small text-uppercase fw-bold m-0">Tables Analyzed</p>
                            </div>
                            <div class="col-md-4 border-end">
                                <h3 id="speed-time-report" class="fw-bold text-success">0 ms</h3>
                                <p class="text-muted small text-uppercase fw-bold m-0">Execution Time</p>
                            </div>
                            <div class="col-md-4">
                                <h3 id="speed-cache-report" class="fw-bold text-success" style="font-size: 1rem; line-height: 2.2rem;">Active</h3>
                                <p class="text-muted small text-uppercase fw-bold m-0">Cache Status</p>
                            </div>
                        </div>

                        <div class="card bg-light border-0 rounded-4">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-2 small text-uppercase">Optimization Log</h6>
                                <div id="speed-report-details" style="max-height: 200px; overflow-y: auto;" class="small font-monospace">
                                </div>
                            </div>
                        </div>

                        <button onclick="location.reload()" class="btn btn-outline-success rounded-pill w-100 mt-4 py-2 fw-bold">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Existing System Maintenance
    $('#startMaintenance').on('click', function() {
        if (!confirm('Are you sure you want to run system optimization? This may briefly slow down the site.')) {
            return;
        }

        const clearLogs = $('#clearLogsCheck').is(':checked');
        
        $('#maintenance-init').fadeOut(300, function() {
            $('#maintenance-progress').fadeIn(300);
            startProcess(clearLogs);
        });
    });

    function startProcess(clearLogs) {
        updateProgress(20, 'Analyzing application cache...');
        
        setTimeout(() => {
            updateProgress(40, 'Clearing session cache and temporary files...');
            
            setTimeout(() => {
                updateProgress(65, 'Optimizing database tables (this may take a moment)...');
                
                $.ajax({
                    url: 'ajax_system_optimize.php',
                    method: 'POST',
                    data: {
                        action: 'full_optimize',
                        clear_logs: clearLogs
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateProgress(100, 'Finalizing report...');
                            setTimeout(() => {
                                showReport(response.report);
                            }, 500);
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error occurred'));
                            location.reload();
                        }
                    },
                    error: function() {
                        alert('Fatal error during optimization.');
                        location.reload();
                    }
                });
            }, 1000);
        }, 1000);
    }

    function updateProgress(percent, text) {
        $('#maintenance-bar').css('width', percent + '%').attr('aria-valuenow', percent);
        $('#status-text').text(text);
    }

    function showReport(report) {
        $('#maintenance-progress').fadeOut(300, function() {
            $('#sessions-report').text(report.sessions_cleared);
            $('#tables-report').text(Object.keys(report.database_optimization).length);
            
            let details = '';
            if (report.logs_cleared && Object.keys(report.logs_cleared).length > 0) {
                details += '<div class="text-danger mb-2 fw-bold">Logs Activity:</div>';
                for (const logTable in report.logs_cleared) {
                    details += '<div class="text-danger mb-1 ms-3">- ' + logTable + ': ' + report.logs_cleared[logTable] + '</div>';
                }
                details += '<div class="mb-3"></div>';
            }
            
            details += '<ul class="list-unstyled mb-0">';
            for (const table in report.database_optimization) {
                details += '<li><i class="fas fa-check text-success me-2"></i>Table `' + table + '`: ' + report.database_optimization[table] + '</li>';
            }
            details += '</ul>';
            
            $('#report-details').html(details);
            $('#maintenance-report').fadeIn(300);
        });
    }

    // New: Boost Website Speed
    $('#startSpeedBoost').on('click', function() {
        $('#speedboost-init').fadeOut(300, function() {
            $('#speedboost-progress').fadeIn(300);
            startSpeedBoostProcess();
        });
    });

    function startSpeedBoostProcess() {
        updateSpeedProgress(25, 'Analyzing database table indexes...');
        
        setTimeout(() => {
            updateSpeedProgress(55, 'Flushing PHP OPcache and priming config cache...');
            
            setTimeout(() => {
                updateSpeedProgress(85, 'Updating static asset versioning headers...');
                
                $.ajax({
                    url: 'ajax_system_optimize.php',
                    method: 'POST',
                    data: {
                        action: 'boost_speed'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateSpeedProgress(100, 'Speed boost complete!');
                            setTimeout(() => {
                                showSpeedReport(response.report);
                            }, 500);
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error occurred'));
                            location.reload();
                        }
                    },
                    error: function() {
                        alert('Fatal error during speed boost.');
                        location.reload();
                    }
                });
            }, 800);
        }, 800);
    }

    function updateSpeedProgress(percent, text) {
        $('#speedboost-bar').css('width', percent + '%').attr('aria-valuenow', percent);
        $('#speedboost-status-text').text(text);
    }

    function showSpeedReport(report) {
        $('#speedboost-progress').fadeOut(300, function() {
            const tableCount = Object.keys(report.analyzed_tables || {}).length;
            $('#speed-tables-report').text(tableCount);
            $('#speed-time-report').text(report.execution_time + ' ms');
            $('#speed-cache-report').text(report.opcache_status.includes('flushed') ? 'Flushed' : 'Primed');
            
            let details = '<div class="text-success mb-2 fw-bold"><i class="fas fa-check-circle me-1"></i> OPcache: ' + report.opcache_status + '</div>';
            details += '<div class="text-primary mb-2 fw-bold"><i class="fas fa-sync me-1"></i> Site Cache Version: ' + report.cache_version + '</div>';
            details += '<div class="fw-bold mb-1 text-dark">Database Tables Analyzed:</div>';
            details += '<ul class="list-unstyled mb-0 ms-2">';
            for (const table in report.analyzed_tables) {
                details += '<li><i class="fas fa-bolt text-warning me-2"></i>`' + table + '`: ' + report.analyzed_tables[table] + '</li>';
            }
            details += '</ul>';
            
            $('#speed-report-details').html(details);
            $('#speedboost-report').fadeIn(300);
        });
    }
});
</script>

<?php
include 'admin_footer.php';
?>

