<?php
include 'admin_header.php';
require_once '../includes/ScriptService.php';

$scriptService = new ScriptService($conn);
$scripts = $scriptService->getRawScripts();
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-code"></i> Script & Tag Injection
            </div>
            <h1 class="adm-hero-title">Insert Headers & Footers</h1>
            <p class="adm-hero-subtitle">Safely inject third-party analytics pixels, live chats, Google Tag Manager snippets, and webmaster site verification tags.</p>
        </div>
        <div class="adm-hero-actions">
            <button type="button" onclick="$('#scriptsForm').submit()" class="adm-btn-white">
                <i class="fas fa-save me-2"></i>Save All Changes
            </button>
        </div>
    </div>

    <div class="adm-card">
        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-code me-2 text-primary"></i>Custom Site Scripts & Metatags</h5>
                <p class="text-muted small mb-0">Code inserted here is placed directly in the site markup on every frontend page load.</p>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-semibold">
                <i class="fas fa-shield-alt me-1"></i>Base64 Protected Injection
            </span>
        </div>
        <div class="card-body p-4">
            <form id="scriptsForm">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="save_scripts">
                
                <!-- Header Section -->
                <div class="section-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="adm-icon-box bg-blue me-3" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Header Scripts (&lt;head&gt;)</h6>
                            <div class="text-muted small">Injected just before closing <code>&lt;/head&gt;</code> tag. Ideal for Google Analytics, Meta Pixel, typography fonts.</div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <textarea name="header_code" class="form-control font-monospace small" rows="7" placeholder="<!-- Add your Google Analytics, Facebook Pixel, or custom CSS here -->"><?php echo htmlspecialchars($scripts['header_code'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Body Section -->
                <div class="section-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="adm-icon-box bg-yellow me-3" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fas fa-code"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Body Scripts (&lt;body&gt;)</h6>
                            <div class="text-muted small">Injected immediately following the opening <code>&lt;body&gt;</code> tag. Ideal for Google Tag Manager (noscript).</div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <textarea name="body_code" class="form-control font-monospace small" rows="7" placeholder="<!-- Add your Google Tag Manager (noscript) or custom HTML here -->"><?php echo htmlspecialchars($scripts['body_code'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Footer Section -->
                <div class="section-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="adm-icon-box bg-green me-3" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Footer Scripts (&lt;/body&gt;)</h6>
                            <div class="text-muted small">Injected right before closing <code>&lt;/body&gt;</code> tag. Ideal for live chat widgets, non-critical scripts.</div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <textarea name="footer_code" class="form-control font-monospace small" rows="7" placeholder="<!-- Add your Live Chat scripts or tracking pixels here -->"><?php echo htmlspecialchars($scripts['footer_code']); ?></textarea>
                    </div>
                </div>

                <!-- Verification Manager -->
                <div class="section-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="adm-icon-box bg-pink me-3" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Search Engine Site Verification</h6>
                            <div class="text-muted small">Quickly verify your domain ownership with major search platforms.</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Google Search Console Verification</label>
                            <input type="text" name="google_verification" class="form-control" value="<?php echo htmlspecialchars($scripts['google_verification']); ?>" placeholder="e.g. your-unique-google-code">
                            <div class="form-text">Only enter the <code>content="..."</code> attribute value from Google.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Bing Webmaster Tools Verification</label>
                            <input type="text" name="bing_verification" class="form-control" value="<?php echo htmlspecialchars($scripts['bing_verification']); ?>" placeholder="e.g. AC6789BC4567...">
                            <div class="form-text">Enter the verification token from Bing Webmaster.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Custom Verification Meta Tags</label>
                            <textarea name="custom_verification" class="form-control font-monospace small" rows="3" placeholder='<meta name="pinterest-site-verification" content="...">\n<meta name="yandex-verification" content="...">'><?php echo htmlspecialchars($scripts['custom_verification']); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">TXT Record / DNS Instructions (Personal Internal Notes)</label>
                            <textarea name="txt_instructions" class="form-control small" rows="3" placeholder="Paste your DNS TXT records here for quick reference..."><?php echo htmlspecialchars($scripts['txt_instructions']); ?></textarea>
                            <div class="form-text text-muted">Internal reference only. Will NOT be rendered on public storefront.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div id="saveStatus" class="small fw-bold"></div>
                    <button type="submit" id="saveBtn" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i>Save Code Snippets
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .section-card {
        background: #f8fafc;
        padding: 22px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
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
            body_code: toB64($('textarea[name="body_code"]').val()),
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
