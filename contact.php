<?php
include 'includes/header.php';
require_once 'includes/mail_functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security check failed. Please submit the form again.";
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($subject) || empty($message)) {
            $error = "Please fill in all required fields.";
        } else {
            $name    = $conn->real_escape_string($name);
            $email   = $conn->real_escape_string($email);
            $phone   = $conn->real_escape_string($phone);
            $subject = $conn->real_escape_string($subject);
            $message = $conn->real_escape_string($message);

    // Get Admin Email from Settings
    $admin_email = isset($global_settings['admin_email']) && !empty($global_settings['admin_email']) ?
                   $global_settings['admin_email'] : 'admin@yoursite.com';

    // Fetch template
    $tpl = getEmailTemplate($conn, 'contact_form');
    if ($tpl) {
        $vars = [
            'name' => htmlspecialchars($name),
            'email' => htmlspecialchars($email),
            'phone' => htmlspecialchars($phone),
            'subject' => htmlspecialchars($subject),
            'message' => nl2br(htmlspecialchars($message))
        ];
        $final_subject = parseTemplate($tpl['subject'], $vars);
        $final_body = parseTemplate($tpl['body'], $vars);
    } else {
        // FALLBACK
        $final_subject = "Contact Form: " . $subject;
        $final_body = "
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
            <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
            <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            <hr>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        ";
    }

    // Attempt to send the email
    $result = sendEmail($admin_email, $final_subject, $final_body);

    $status  = $result['success'] ? 'Success' : 'Failed';
    $err_msg = $result['success'] ? '' : $conn->real_escape_string($result['error']);

    // Log the transaction
    $conn->query("INSERT INTO email_logs (recipient_email, email_type, status, error_message) VALUES ('$admin_email', 'contact_form', '$status', '$err_msg')");

    if ($result['success']) {
        $success = htmlspecialchars($global_settings['contact_success_msg'] ?? 'Thank you for reaching out! We will get back to you shortly.');
    } else {
        $error = "Sorry, there was a problem sending your message. Error Details: " . $result['error'];
    }
    }
    }
}

// ── Dynamic contact page values (with fallbacks) ─────────────
$c_hero_title    = $global_settings['contact_hero_title']    ?? 'Contact Us';
$c_hero_subtitle = $global_settings['contact_hero_subtitle'] ?? "We'd love to hear from you. Get in touch with us!";
$c_hero_gradient = $global_settings['contact_hero_gradient'] ?? 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)';
$c_sec_title     = $global_settings['contact_section_title'] ?? 'Get In Touch';
$c_sec_desc      = $global_settings['contact_section_desc']  ?? "Have a question about a product, your order, or just want to say hi? Contact us below and we will get back to you as soon as possible!";
$c_address       = $global_settings['contact_address']       ?? '123 Modern Avenue, NY 10001, USA';
$c_phone         = $global_settings['contact_phone']         ?? '+1 (555) 123-4567';
$c_email         = $global_settings['contact_email']         ?? 'support@modernstore.com';
$c_hours         = $global_settings['contact_hours']         ?? 'Mon–Sat: 9am – 6pm';
$c_lbl_address   = $global_settings['contact_label_address'] ?? 'Our Location';
$c_lbl_phone     = $global_settings['contact_label_phone']   ?? 'Phone Number';
$c_lbl_email     = $global_settings['contact_label_email']   ?? 'Email Address';
$c_lbl_hours     = $global_settings['contact_label_hours']   ?? 'Business Hours';
$c_form_title    = $global_settings['contact_form_title']    ?? 'Send us a Message';
$c_form_btn      = $global_settings['contact_form_btn']      ?? 'Send Message';
$c_map_show      = $global_settings['contact_map_show']      ?? '0';
$c_map_embed     = $global_settings['contact_map_embed']     ?? '';
// Colours & Typography settings
$c_heading_color    = $global_settings['contact_heading_color']     ?? '#0d6efd';
$c_heading_fs       = intval($global_settings['contact_heading_font_size'] ?? 28);
$c_body_fs          = intval($global_settings['contact_body_font_size']    ?? 15);
$c_icon_bg          = $global_settings['contact_icon_bg_color']     ?? '#e8f0fe';
$c_icon_color       = $global_settings['contact_icon_color']        ?? '#0d6efd';
$c_form_card_bg     = $global_settings['contact_form_card_bg']      ?? '#ffffff';
// If a full <iframe> tag was stored, extract the src URL using double-quote boundary
// (Google iframe always uses double quotes). Extract BEFORE decoding entities so that
// internal &#39; characters inside the URL don't break the regex delimiter.
if (!empty($c_map_embed)) {
    if (stripos($c_map_embed, '<iframe') !== false) {
        // Match src="..." using only double-quote delimiter
        if (preg_match('/src="([^"]+)"/', $c_map_embed, $m)) {
            $c_map_embed = $m[1];
        } else {
            $c_map_embed = '';
        }
    }
    // Decode any remaining HTML entities in the URL (&amp; → &, &#39; → ', %3A stays as-is)
    $c_map_embed = html_entity_decode($c_map_embed, ENT_QUOTES, 'UTF-8');
}
?>

<?php 
$hero_bg_style = "background: " . htmlspecialchars($c_hero_gradient) . " !important;";
if (!empty($global_settings['hero_banner_contact'])) {
    $img_url = htmlspecialchars(resolve_image_url($global_settings['hero_banner_contact']));
    if (!empty($img_url) && strpos($img_url, 'placeholder') === false) {
        $hero_bg_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
    }
}
?>
<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-5" style="<?php echo $hero_bg_style; ?>">
    <div class="container py-5 text-center">
        <h1 class="display-4 fw-bold mb-3 montserrat"><?php echo htmlspecialchars($c_hero_title); ?></h1>
        <p class="lead mb-0"><?php echo htmlspecialchars($c_hero_subtitle); ?></p>
    </div>
</div>

<div class="container mb-5">
    <div class="row">
        <!-- Contact Information -->
        <div class="col-md-5 mb-4 mb-md-0 pe-md-5">
            <h3 class="fw-bold mb-4 montserrat" style="color:<?php echo htmlspecialchars($c_heading_color); ?> !important; font-size:<?php echo $c_heading_fs; ?>px !important;"><?php echo htmlspecialchars($c_sec_title); ?></h3>
            <p class="text-muted mb-5" style="font-size:<?php echo $c_body_fs; ?>px !important;"><?php echo htmlspecialchars($c_sec_desc); ?></p>

            <!-- Address -->
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background-color:<?php echo htmlspecialchars($c_icon_bg); ?> !important;">
                    <i class="fas fa-map-marker-alt fa-2x" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($c_lbl_address); ?></h5>
                    <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($c_address)); ?></p>
                </div>
            </div>

            <!-- Phone -->
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background-color:<?php echo htmlspecialchars($c_icon_bg); ?> !important;">
                    <i class="fas fa-phone fa-2x" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($c_lbl_phone); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($c_phone); ?></p>
                </div>
            </div>

            <!-- Email -->
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background-color:<?php echo htmlspecialchars($c_icon_bg); ?> !important;">
                    <i class="fas fa-envelope fa-2x" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($c_lbl_email); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($c_email); ?></p>
                </div>
            </div>

            <!-- Business Hours -->
            <?php if (!empty($c_hours)): ?>
            <div class="d-flex align-items-center">
                <div class="rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background-color:<?php echo htmlspecialchars($c_icon_bg); ?> !important;">
                    <i class="fas fa-clock fa-2x" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($c_lbl_hours); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($c_hours); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Contact Form -->
        <div class="col-md-7">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5" style="background-color:<?php echo htmlspecialchars($c_form_card_bg); ?> !important;">
                <h4 class="fw-bold mb-4 text-center montserrat"><?php echo htmlspecialchars($c_form_title); ?></h4>

                <?php if ($success): ?>
                    <div class="alert alert-success py-3 text-center rounded-3">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-3 text-center rounded-3">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Your Name</label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="John Doe" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="john@example.com" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Phone Number</label>
                        <?php echo render_phone_input('phone', '', true, 'form-control-lg'); ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control form-control-lg" placeholder="How can we help?" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" class="form-control form-control-lg" rows="5" placeholder="Your message here..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-custom btn-lg w-100">
                        <?php echo htmlspecialchars($c_form_btn); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Google Maps Embed (optional) -->
    <?php if ($c_map_show == '1' && !empty($c_map_embed)): ?>
    <div class="mt-5">
        <iframe src="<?php echo htmlspecialchars($c_map_embed); ?>"
                width="100%" height="400"
                style="border:0; border-radius:16px; display:block;"
                allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>
