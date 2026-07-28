<?php
include 'admin_header.php';
require_once '../includes/ScriptService.php';

$scriptService = new ScriptService($conn);
$scripts = $scriptService->getRawScripts();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-code me-2"></i>Insert Headers & Footers</h4>
                    <p class="text-muted small">Add custom tracking codes, meta tags, and verification scripts to your website.</p>
                </div>
                <div class="card-body p-4">
                    <form id="scriptsForm">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_scripts">
                        
                        <!-- Header Section -->
                        <div class="section-card mb-4">
                            <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="fas fa-arrow-up me-2 text-primary"></i>Header Code</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Scripts in &lt;head&gt;</label>
                                <textarea name="header_code" class="form-control font-monospace small" rows="8" placeholder="<!-- Add your Google Analytics, Facebook Pixel, or custom CSS here -->"><?php echo htmlspecialchars($scripts['header_code']); ?></textarea>
                                <div class="form-text">These scripts will be printed inside the <code>&lt;head&gt;</code> section.</div>
                            </div>
                        </div>

                        <!-- Footer Section -->
                        <div class="section-card mb-4">
                            <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="fas fa-arrow-down me-2 text-primary"></i>Footer Code</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Scripts before &lt;/body&gt;</label>
                                <textarea name="footer_code" class="form-control font-monospace small" rows="8" placeholder="<!-- Add your Live Chat scripts or tracking pixels here -->"><?php echo htmlspecialchars($scripts['footer_code']); ?></textarea>
                                <div class="form-text">These scripts will be printed just before the <code>&lt;/body&gt;</code> tag.</div>
                            </div>
                        </div>

                        <!-- Verification Manager -->
                        <div class="section-card mb-4">
                            <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="fas fa-check-shield me-2 text-primary"></i>Verification Manager</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Google Search Console Verification</label>
                                    <input type="text" name="google_verification" class="form-control" value="<?php echo htmlspecialchars($scripts['google_verification']); ?>" placeholder="e.g. your-unique-google-code">
                                    <div class="form-text">Only enter the <code>content="..."</code> value from the meta tag.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Bing Webmaster Tools Verification</label>
                                    <input type="text" name="bing_verification" class="form-control" value="<?php echo htmlspecialchars($scripts['bing_verification']); ?>" placeholder="e.g. AC6789BC4567...">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Custom Verification Meta Tags</label>
                                    <textarea name="custom_verification" class="form-control font-monospace small" rows="3" placeholder='<meta name="other-service" content="...">'><?php echo htmlspecialchars($scripts['custom_verification']); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">TXT Record / DNS Instructions (Personal Notes)</label>
                                    <textarea name="txt_instructions" class="form-control small" rows="3" placeholder="Paste your DNS TXT records here for quick reference..."><?php echo htmlspecialchars($scripts['txt_instructions']); ?></textarea>
                                    <div class="form-text text-muted">This field is for your reference only and will NOT be rendered on the frontend.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div id="saveStatus" class="small fw-bold"></div>
                            <button type="submit" id="saveBtn" class="btn btn-primary btn-custom px-5">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .section-card {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #3b5976;
    }
</style>

<script>
function toB64(str) {
    if (!str) return '';
    try {
        return btoa(unescape(encodeURIComponent(str)));
    } catch(e) {
        return str;
    }
}

$(document).ready(function() {
    $('#scriptsForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#saveBtn');
        const status = $('#saveStatus');
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
        status.removeClass('text-success text-danger').text('');

        const formData = {
            action: 'save_scripts',
            is_b64: '1',
            _csrf_token: $('input[name="_csrf_token"]').val(),
            header_code: toB64($('textarea[name="header_code"]').val()),
            footer_code: toB64($('textarea[name="footer_code"]').val()),
            google_verification: $('input[name="google_verification"]').val(),
            bing_verification: $('input[name="bing_verification"]').val(),
            custom_verification: toB64($('textarea[name="custom_verification"]').val()),
            txt_instructions: toB64($('textarea[name="txt_instructions"]').val())
        };

        $.ajax({
            url: 'ajax_manage_scripts.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    status.addClass('text-success').text('Settings saved successfully!');
                    setTimeout(() => status.fadeOut(tmp => status.text('').show()), 3000);
                } else {
                    status.addClass('text-danger').text('Error: ' + (response.error || 'Failed to save settings'));
                }
            },
            error: function(xhr) {
                let msg = 'Critical error communicating with server.';
                if (xhr.status === 403) {
                    msg = 'Security verification failed or session expired. Please refresh the page and try again.';
                }
                status.addClass('text-danger').text(msg);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Settings');
            }
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
