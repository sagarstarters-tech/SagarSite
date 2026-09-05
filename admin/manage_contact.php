<?php
include 'admin_header.php';

// ── All Contact Us page settings with defaults ───────────────
$contact_keys = [
    // Hero
    'contact_hero_title'        => 'Contact Us',
    'contact_hero_subtitle'     => "We'd love to hear from you. Get in touch with us!",
    'contact_hero_gradient'     => 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',

    // Get In Touch panel
    'contact_section_title'     => 'Get In Touch',
    'contact_section_desc'      => "Have a question about a product, your order, or just want to say hi? Contact us below and we will get back to you as soon as possible!",

    // Contact details
    'contact_address'           => '123 Modern Avenue, NY 10001, USA',
    'contact_phone'             => '+1 (555) 123-4567',
    'contact_email'             => 'support@modernstore.com',
    'contact_hours'             => 'Mon–Sat: 9am – 6pm',

    // Contact info labels
    'contact_label_address'     => 'Our Location',
    'contact_label_phone'       => 'Phone Number',
    'contact_label_email'       => 'Email Address',
    'contact_label_hours'       => 'Business Hours',

    // Form
    'contact_form_title'        => 'Send us a Message',
    'contact_form_btn'          => 'Send Message',
    'contact_success_msg'       => 'Thank you for reaching out! We will get back to you shortly.',

    // Map embed
    'contact_map_embed'         => '',
    'contact_map_show'          => '0',

    // ── Colours & Typography (new) ──────────────────────────
    'contact_heading_color'     => '#0d6efd',
    'contact_heading_font_size' => '28',
    'contact_body_font_size'    => '15',
    'contact_icon_bg_color'     => '#e8f0fe',
    'contact_icon_color'        => '#0d6efd',
    'contact_form_card_bg'      => '#ffffff',
];

// ── POST Handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($contact_keys as $key => $default) {
        if (!isset($_POST[$key])) continue;
        $value = $_POST[$key];

        // Special handling: if admin pasted a full <iframe> embed, extract just the src URL.
        // Use double-quote-only regex (Google Maps iframes always use double quotes for src)
        // so that apostrophes in business names inside the URL don't truncate it.
        if ($key === 'contact_map_embed') {
            // Strip HTML entities like &amp; / &#39; but do it AFTER extracting with dbl-quote regex
            if (stripos($value, '<iframe') !== false) {
                if (preg_match('/src="([^"]+)"/', $value, $m)) {
                    $value = $m[1];
                } else {
                    $value = '';
                }
            }
            // Decode any HTML entities left in the URL
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            // Strip any stray HTML tags
            $value = strip_tags($value);
        }

        $value = $conn->real_escape_string($value);
        $conn->query("INSERT INTO settings (setting_key, setting_value)
                      VALUES ('$key', '$value')
                      ON DUPLICATE KEY UPDATE setting_value='$value'");
    }
    $success = 'Contact Us page updated successfully.';
    // Refresh global settings
    $sq = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $sq->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// ── Merge saved values over defaults ─────────────────────────
$cs = $contact_keys; // start with defaults
foreach ($contact_keys as $key => $default) {
    if (isset($global_settings[$key]) && $global_settings[$key] !== '') {
        $cs[$key] = $global_settings[$key];
    }
}

function cv($cs, $key) {
    return htmlspecialchars($cs[$key] ?? '');
}
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-headset"></i> Customer Communication
            </div>
            <h1 class="adm-hero-title">Contact Us Page Customizer</h1>
            <p class="adm-hero-subtitle">Manage support addresses, helpdesk telephone numbers, business hours, and interactive maps.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="../contact.php" target="_blank" class="adm-btn-white">
                <i class="fas fa-external-link-alt me-2"></i>View Live Page
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_input(); ?>

        <!-- ── Hero Section ──────────────────────────────────────── -->
        <div class="col-md-12 mb-4">
            <div class="adm-card">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-image me-2 text-primary"></i>Hero Section</h6>
                </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Hero Title</label>
                        <input type="text" name="contact_hero_title" class="form-control" value="<?php echo cv($cs,'contact_hero_title'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Hero Subtitle</label>
                        <input type="text" name="contact_hero_subtitle" class="form-control" value="<?php echo cv($cs,'contact_hero_subtitle'); ?>">
                    </div>
                    <div class="col-md-12 mb-0">
                        <label class="form-label fw-bold">Hero Background Gradient
                            <small class="text-muted ms-1 fw-normal">(CSS gradient value)</small>
                        </label>
                        <input type="text" name="contact_hero_gradient" class="form-control" value="<?php echo cv($cs,'contact_hero_gradient'); ?>" placeholder="linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)">
                        <small class="text-muted">Paste any CSS gradient. Preview: <span id="gradPreview" style="display:inline-block;width:120px;height:18px;border-radius:4px;vertical-align:middle;"></span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Get In Touch Text ─────────────────────────────────── -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-hand-point-right me-2"></i>Get In Touch Panel</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Panel Heading</label>
                            <input type="text" name="contact_section_title" class="form-control" value="<?php echo cv($cs,'contact_section_title'); ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Panel Description</label>
                            <textarea name="contact_section_desc" class="form-control" rows="2"><?php echo cv($cs,'contact_section_desc'); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Contact Details ───────────────────────────────────── -->
    <div class="row mb-4 g-4">
        <!-- Address -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-map-marker-alt me-2"></i>Address</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Label Text</label>
                        <input type="text" name="contact_label_address" class="form-control" value="<?php echo cv($cs,'contact_label_address'); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="contact_address" class="form-control" rows="3"><?php echo cv($cs,'contact_address'); ?></textarea>
                        <small class="text-muted">Use new lines for multi-line address.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phone & Email -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-phone me-2"></i>Phone & Email</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Label</label>
                        <input type="text" name="contact_label_phone" class="form-control" value="<?php echo cv($cs,'contact_label_phone'); ?>">
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold d-block">Phone Number</label>
                        <?php echo render_phone_input('contact_phone', $cs['contact_phone'] ?? '', true); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Label</label>
                        <input type="text" name="contact_label_email" class="form-control" value="<?php echo cv($cs,'contact_label_email'); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Display Email</label>
                        <input type="text" name="contact_email" class="form-control" value="<?php echo cv($cs,'contact_email'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Hours -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-clock me-2"></i>Business Hours</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Hours Label</label>
                        <input type="text" name="contact_label_hours" class="form-control" value="<?php echo cv($cs,'contact_label_hours'); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Hours Text</label>
                        <input type="text" name="contact_hours" class="form-control" value="<?php echo cv($cs,'contact_hours'); ?>" placeholder="Mon–Sat: 9am – 6pm">
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Embed -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-map me-2"></i>Google Maps Embed</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="contact_map_show" id="showMapToggle" value="1"
                                   <?php echo ($cs['contact_map_show'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label fs-6 ms-2" for="showMapToggle">Show Map on Page</label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Google Maps Embed URL</label>
                        <input type="text" name="contact_map_embed" class="form-control" value="<?php echo cv($cs,'contact_map_embed'); ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                        <small class="text-muted">Paste the <strong>full embed code</strong> (the entire &lt;iframe&gt; tag) <strong>or</strong> just the <code>src</code> URL. Both work. Go to Google Maps → Share → Embed a map → Copy the embed code.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Form Settings ─────────────────────────────────────── -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-envelope me-2"></i>Contact Form Texts</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Form Card Title</label>
                        <input type="text" name="contact_form_title" class="form-control" value="<?php echo cv($cs,'contact_form_title'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Submit Button Text</label>
                        <input type="text" name="contact_form_btn" class="form-control" value="<?php echo cv($cs,'contact_form_btn'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Success Message</label>
                        <input type="text" name="contact_success_msg" class="form-control" value="<?php echo cv($cs,'contact_success_msg'); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Colours & Typography ─────────────────────────────── -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-palette me-2"></i>Colours &amp; Typography</h6>
            </div>
            <div class="card-body">

                <!-- Colour pickers row -->
                <div class="row g-4 mb-4">

                    <!-- Heading Colour -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1">Section Heading Colour</label>
                        <small class="d-block text-muted mb-2">&ldquo;Get In Touch&rdquo; title colour.</small>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color"
                                   name="contact_heading_color"
                                   id="contact_heading_color"
                                   value="<?php echo cv($cs,'contact_heading_color'); ?>"
                                   class="contact-color-picker"
                                   style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;">
                            <input type="text"
                                   class="form-control form-control-sm contact-hex-input"
                                   data-target="contact_heading_color"
                                   value="<?php echo cv($cs,'contact_heading_color'); ?>"
                                   maxlength="7" placeholder="#0d6efd"
                                   style="max-width:110px;font-family:monospace;">
                        </div>
                    </div>

                    <!-- Icon Background Colour -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1">Icon Circle Background</label>
                        <small class="d-block text-muted mb-2">Background of address / phone / email icons.</small>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color"
                                   name="contact_icon_bg_color"
                                   id="contact_icon_bg_color"
                                   value="<?php echo cv($cs,'contact_icon_bg_color'); ?>"
                                   class="contact-color-picker"
                                   style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;">
                            <input type="text"
                                   class="form-control form-control-sm contact-hex-input"
                                   data-target="contact_icon_bg_color"
                                   value="<?php echo cv($cs,'contact_icon_bg_color'); ?>"
                                   maxlength="7" placeholder="#e8f0fe"
                                   style="max-width:110px;font-family:monospace;">
                        </div>
                    </div>

                    <!-- Icon Colour -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1">Icon Colour</label>
                        <small class="d-block text-muted mb-2">Colour of the map / phone / email icons.</small>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color"
                                   name="contact_icon_color"
                                   id="contact_icon_color"
                                   value="<?php echo cv($cs,'contact_icon_color'); ?>"
                                   class="contact-color-picker"
                                   style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;">
                            <input type="text"
                                   class="form-control form-control-sm contact-hex-input"
                                   data-target="contact_icon_color"
                                   value="<?php echo cv($cs,'contact_icon_color'); ?>"
                                   maxlength="7" placeholder="#0d6efd"
                                   style="max-width:110px;font-family:monospace;">
                        </div>
                    </div>

                    <!-- Form Card Background -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1">Form Card Background</label>
                        <small class="d-block text-muted mb-2">Background colour of the &ldquo;Send a Message&rdquo; card.</small>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color"
                                   name="contact_form_card_bg"
                                   id="contact_form_card_bg"
                                   value="<?php echo cv($cs,'contact_form_card_bg'); ?>"
                                   class="contact-color-picker"
                                   style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;">
                            <input type="text"
                                   class="form-control form-control-sm contact-hex-input"
                                   data-target="contact_form_card_bg"
                                   value="<?php echo cv($cs,'contact_form_card_bg'); ?>"
                                   maxlength="7" placeholder="#ffffff"
                                   style="max-width:110px;font-family:monospace;">
                        </div>
                    </div>

                </div><!-- /colour row -->

                <hr class="my-3">
                <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-text-height me-2"></i>Typography</h6>

                <div class="row g-4">

                    <!-- Heading Font Size -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="contact_heading_font_size">
                            Section Heading Font Size
                            <span class="badge bg-primary ms-2" id="contactHeadingFsBadge"><?php echo intval($cs['contact_heading_font_size']); ?>px</span>
                        </label>
                        <small class="d-block text-muted mb-2">Font size of &ldquo;Get In Touch&rdquo; heading.</small>
                        <input type="range" class="form-range" name="contact_heading_font_size" id="contact_heading_font_size"
                               min="16" max="56" step="1"
                               value="<?php echo intval($cs['contact_heading_font_size']); ?>"
                               oninput="document.getElementById('contactHeadingFsBadge').textContent=this.value+'px'">
                        <div class="d-flex justify-content-between text-muted small">
                            <span>16px (Small)</span><span>28px (Default)</span><span>56px (Large)</span>
                        </div>
                    </div>

                    <!-- Body Font Size -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="contact_body_font_size">
                            Body / Description Font Size
                            <span class="badge bg-primary ms-2" id="contactBodyFsBadge"><?php echo intval($cs['contact_body_font_size']); ?>px</span>
                        </label>
                        <small class="d-block text-muted mb-2">Paragraph &amp; description text size on the page.</small>
                        <input type="range" class="form-range" name="contact_body_font_size" id="contact_body_font_size"
                               min="12" max="24" step="1"
                               value="<?php echo intval($cs['contact_body_font_size']); ?>"
                               oninput="document.getElementById('contactBodyFsBadge').textContent=this.value+'px'">
                        <div class="d-flex justify-content-between text-muted small">
                            <span>12px (Small)</span><span>15px (Default)</span><span>24px (Large)</span>
                        </div>
                    </div>

                </div><!-- /typography row -->

            </div><!-- /card-body -->
        </div><!-- /card -->
    </div><!-- /col -->

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary btn-lg btn-custom px-5">
            <i class="fas fa-save me-2"></i>Save All Changes
        </button>
    </div>
</form>

<script>
// Colour picker ↔ Hex text sync for contact page colour pickers
(function () {
    document.querySelectorAll('.contact-color-picker').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var key = picker.id;
            var hexInput = document.querySelector('.contact-hex-input[data-target="' + key + '"]');
            if (hexInput) hexInput.value = picker.value;
        });
    });
    document.querySelectorAll('.contact-hex-input').forEach(function (input) {
        input.addEventListener('input', function () {
            var key = input.dataset.target;
            if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                var picker = document.getElementById(key);
                if (picker) picker.value = input.value;
            }
        });
    });
})();
</script>

<script>
// Live gradient preview
(function () {
    var input = document.querySelector('[name="contact_hero_gradient"]');
    var preview = document.getElementById('gradPreview');
    function update() { if (preview) preview.style.background = input.value; }
    if (input) { input.addEventListener('input', update); update(); }
})();
</script>
</div>
<?php include 'admin_footer.php'; ?>
