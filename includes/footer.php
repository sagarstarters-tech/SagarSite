<footer style="background-color: var(--footer-bg, #ebebeb); color: var(--text-color, #333);" class="pt-5 pb-3">
<?php
// Handle subscription form
$sub_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_email'])) {
    $email = $conn->real_escape_string(trim($_POST['subscribe_email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sub_msg = '<div class="alert alert-danger mt-2 py-1 small">Invalid email address.</div>';
    } else {
        // Create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $insert_q = "INSERT IGNORE INTO subscribers (email) VALUES ('$email')";
        if ($conn->query($insert_q)) {
            $sub_msg = '<div class="alert alert-success mt-2 py-1 small">Subscribed successfully!</div>';
        } else {
            $sub_msg = '<div class="alert alert-warning mt-2 py-1 small">You are already subscribed.</div>';
        }
    }
}
?>
  <div class="container">
    <div class="row mb-5 mt-3">
        <!-- Column 1: Brand & Slogan -->
        <div class="col-lg-4 col-md-6 mb-4 pe-lg-5">
            <?php if (!isset($global_settings['show_footer_logo']) || $global_settings['show_footer_logo'] == '1'): ?>
                <img src="<?php echo htmlspecialchars(resolve_image_url($global_settings['footer_logo_image'] ?? 'logo.jpg')); ?>" alt="Sagar Starter's" width="160" height="<?php echo htmlspecialchars($global_settings['footer_logo_height'] ?? '45'); ?>" loading="lazy" decoding="async" style="height: <?php echo htmlspecialchars($global_settings['footer_logo_height'] ?? '45'); ?>px; width: auto; object-fit: contain;" class="mb-4">
            <?php else: ?>
                <h4 class="fw-bold mb-3 text-dark">Sagar Starter's</h4>
            <?php endif; ?>
            
            <?php if(!empty($global_settings['footer_text'])): ?>
                <p class="small mb-3"><?php echo nl2br(htmlspecialchars($global_settings['footer_text'])); ?></p>
            <?php endif; ?>
            
            <div class="d-flex mt-4">
                <?php if(!empty($global_settings['social_facebook'])): ?>
                    <a href="<?php echo htmlspecialchars($global_settings['social_facebook']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-circle me-3" aria-label="Follow us on Facebook"><i class="fab fa-facebook-f"></i></a>
                <?php endif; ?>
                
                <?php if(!empty($global_settings['social_twitter'])): ?>
                    <a href="<?php echo htmlspecialchars($global_settings['social_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-circle me-3" aria-label="Follow us on Twitter"><i class="fab fa-twitter"></i></a>
                <?php endif; ?>
                
                <?php if(!empty($global_settings['social_instagram'])): ?>
                    <a href="<?php echo htmlspecialchars($global_settings['social_instagram']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-circle me-3" aria-label="Follow us on Instagram"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                
                <?php if(!empty($global_settings['social_linkedin'])): ?>
                    <a href="<?php echo htmlspecialchars($global_settings['social_linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-circle" aria-label="Follow us on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Column 2: Footer 1 / For Him -->
        <div class="col-lg-3 col-md-6 mb-4">
            <h5 class="mb-4 fw-normal"><?php echo htmlspecialchars($global_settings['footer_col2_title'] ?? 'For Him'); ?></h5>
            <ul class="list-unstyled">
                <?php
                $f1_q = $conn->query("SELECT * FROM menus WHERE menu_location IN ('footer1', 'both1') ORDER BY order_index ASC");
                if ($f1_q && $f1_q->num_rows > 0) {
                    while($f1 = $f1_q->fetch_assoc()) {
                        $f1_url = $f1['url'];
                        // Prepend SITE_URL to relative URLs (starting with /) so they work in subdirectories like /store
                        if (!empty($f1_url) && $f1_url[0] === '/' && !preg_match('#^https?://#i', $f1_url)) {
                            $f1_url = rtrim(SITE_URL, '/') . $f1_url;
                        }
                        echo '<li class="mb-2"><a href="'.htmlspecialchars($f1_url).'" class="footer-link">'.htmlspecialchars($f1['name']).'</a></li>';
                    }
                } else {
                    echo '<li class="text-muted small">No links added.</li>';
                }
                ?>
            </ul>
        </div>
        
        <!-- Column 3: Footer 2 / Support -->
        <div class="col-lg-3 col-md-6 mb-4">
            <h5 class="mb-4 fw-normal"><?php echo htmlspecialchars($global_settings['footer_col3_title'] ?? 'Support'); ?></h5>
            <ul class="list-unstyled">
                <?php
                $f2_q = $conn->query("SELECT * FROM menus WHERE menu_location IN ('footer2', 'both2') ORDER BY order_index ASC");
                if ($f2_q && $f2_q->num_rows > 0) {
                    while($f2 = $f2_q->fetch_assoc()) {
                        $f2_url = $f2['url'];
                        // Prepend SITE_URL to relative URLs (starting with /) so they work in subdirectories like /store
                        if (!empty($f2_url) && $f2_url[0] === '/' && !preg_match('#^https?://#i', $f2_url)) {
                            $f2_url = rtrim(SITE_URL, '/') . $f2_url;
                        }
                        echo '<li class="mb-2"><a href="'.htmlspecialchars($f2_url).'" class="footer-link">'.htmlspecialchars($f2['name']).'</a></li>';
                    }
                } else {
                    echo '<li class="text-muted small">No links added.</li>';
                }
                ?>
            </ul>
        </div>
        
        <!-- Column 4: Send Me -->
        <div class="col-lg-2 col-md-6 mb-4">
            <h5 class="mb-4 fw-normal"><?php echo htmlspecialchars($global_settings['footer_col4_title'] ?? 'Send Me'); ?></h5>
            <ul class="list-unstyled">
                <?php
                $f3_q = $conn->query("SELECT * FROM menus WHERE menu_location IN ('footer3', 'both3') ORDER BY order_index ASC");
                if ($f3_q && $f3_q->num_rows > 0) {
                    while($f3 = $f3_q->fetch_assoc()) {
                        $f3_url = $f3['url'];
                        // Prepend SITE_URL to relative URLs (starting with /) so they work in subdirectories like /store
                        if (!empty($f3_url) && $f3_url[0] === '/' && !preg_match('#^https?://#i', $f3_url)) {
                            $f3_url = rtrim(SITE_URL, '/') . $f3_url;
                        }
                        echo '<li class="mb-2"><a href="'.htmlspecialchars($f3_url).'" class="footer-link">'.htmlspecialchars($f3['name']).'</a></li>';
                    }
                } else {
                    echo '<li class="text-muted small">No links added.</li>';
                }
                ?>
            </ul>
        </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="row border-top pt-4" style="border-color: #dbdbdb !important;">
        <div class="col-12 text-center">
            <span class="small">
                <?php echo $global_settings['footer_copyright'] ?? ("Copyright &copy; " . date("Y") . " Sagar Starter's. Powered by Sagar Starter's."); ?>
            </span>
        </div>
    </div>
  </div>
</footer>

<!-- MDB JS -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.js" defer></script>

<!-- Custom JS (Vanilla JS, 0 dependencies) -->
<script src="<?php echo ASSETS_URL; ?>/js/main.js" defer></script>
<!-- Animations Custom JS -->
<script src="<?php echo ASSETS_URL; ?>/js/animations.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show Password Toggle Logic
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('show-password-toggle')) {
            // Find the nearest container that might hold the password field (mb-3, mb-4, or same parent)
            const parent = e.target.closest('.mb-3') || e.target.closest('.mb-4') || e.target.parentElement;
            const input = parent.querySelector('input[type="password"], input[data-show-pw="true"]');
            
            if (input) {
                if (e.target.checked) {
                    input.type = 'text';
                    input.setAttribute('data-show-pw', 'true'); // track it if it has been toggled
                } else {
                    input.type = 'password';
                }
            }
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Phone Country Code Logic
    const phoneGroups = document.querySelectorAll('.phone-group');
    
    phoneGroups.forEach(group => {
        const select = group.querySelector('.country-code-select');
        const input = group.querySelector('.phone-main-input');
        const hidden = group.querySelector('.phone-hidden-final');
        
        function updateHidden() {
            const val = input.value.trim().replace(/\D/g, '');
            if (val) {
                hidden.value = select.value + val;
            } else {
                hidden.value = '';
            }
        }
        
        select.addEventListener('change', updateHidden);
        input.addEventListener('input', updateHidden);
        
        // Initial sync if editing
        if (input.value) updateHidden();
    });
});
</script>

<?php include __DIR__ . '/bottom_nav.php'; ?>

<!-- WhatsApp Chat Widget Integration -->
<?php
$wa_widget_q = $conn->query("SELECT chat_widget_enabled, chat_widget_number, chat_widget_message FROM whatsapp_settings WHERE id = 1");
$wa_widget   = $wa_widget_q ? $wa_widget_q->fetch_assoc() : null;
if ($wa_widget && $wa_widget['chat_widget_enabled']) {
    $widget_raw_num = !empty($wa_widget['chat_widget_number']) ? $wa_widget['chat_widget_number'] : (function_exists('get_store_whatsapp_number') ? get_store_whatsapp_number() : '918573934013');
    $wa_number  = htmlspecialchars($widget_raw_num);
    $wa_message = htmlspecialchars($wa_widget['chat_widget_message'] ?? 'Hello, I have a question about your products.');
    $clean_wa_widget_num = preg_replace('/[^0-9]/', '', $widget_raw_num);
    if (strpos($clean_wa_widget_num, '0') === 0) $clean_wa_widget_num = ltrim($clean_wa_widget_num, '0');
    if (strlen($clean_wa_widget_num) === 10) $clean_wa_widget_num = '91' . $clean_wa_widget_num;
    include BASE_PATH . '/whatsapp-button.html';
    echo "<script>
window.WA_WIDGET_CONFIG = {
    phoneNumber: " . json_encode(!empty($clean_wa_widget_num) ? $clean_wa_widget_num : '918573934013') . ",
    prefillMessage: " . json_encode($wa_widget['chat_widget_message'] ?: 'Hello, I have a question about your products.') . "
};
</script>";
    echo '<script src="' . SITE_URL . '/whatsapp-script.js" defer></script>';
}
?>

<?php
if (isset($scriptService)) {
    echo $scriptService->getFooterScripts();
}
?>
<?php 
// Show Mandatory Profile Completion Modal structure (triggered via JS/AJAX)
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page !== 'profile.php'): ?>
<div class="modal fade" id="profileCompletionModal" tabindex="-1" aria-labelledby="profileCompletionModalLabel" aria-hidden="true" data-mdb-backdrop="static" data-mdb-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-primary text-white border-0 py-3 shadow-none">
        <h5 class="modal-title montserrat fw-bold" id="profileCompletionModalLabel"><i class="fas fa-id-card me-2"></i>Complete Your Profile</h5>
      </div>
      <div class="modal-body text-center p-4 p-md-5">
        <div class="mb-4">
             <div class="d-inline-flex p-4 rounded-circle bg-primary bg-opacity-10 mb-3">
                <i class="fas fa-user-edit fa-3x text-primary"></i>
             </div>
        </div>
        <h3 class="fw-bold mb-3 montserrat">Almost There!</h3>
        <p class="text-muted fs-5 mb-0">To continue shopping and ensure smooth delivery, please take a moment to complete your profile details (Phone, Address, etc.).</p>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-5 pt-0">
        <a href="<?php echo SITE_URL; ?>/user/profile.php" class="btn btn-primary btn-lg btn-custom px-5 py-3 fs-5 shadow-lg rounded-pill" style="min-width: 250px;">
            <i class="fas fa-arrow-right me-2"></i>Go to Profile
        </a>
      </div>
    </div>
  </div>
</div>
<style>
#profileCompletionModal .btn-custom {
    transition: all 0.3s ease;
}
#profileCompletionModal .btn-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
}
</style>
<script>
// Expose a global function for header.php's refreshUserState to call
window.triggerProfileCompletionModal = function() {
    let attempts = 0;
    const interval = setInterval(function() {
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            clearInterval(interval);
            const modalEl = document.getElementById('profileCompletionModal');
            if (modalEl) {
                // Check if already open to prevent double initialization issues
                if (!modalEl.classList.contains('show')) {
                    const myModal = new mdb.Modal(modalEl);
                    myModal.show();
                }
            }
        }
        attempts++;
        if (attempts > 50) clearInterval(interval); // Timeout after 5 seconds
    }, 100);
};

// Also trigger immediately if session check was true initially (in case AJAX fails)
<?php if (isset($_SESSION['user_id']) && isset($_SESSION['needs_profile_update']) && $_SESSION['needs_profile_update'] === true): ?>
document.addEventListener('DOMContentLoaded', window.triggerProfileCompletionModal);
<?php endif; ?>
</script>
<?php endif; ?>

<!-- AI ChatBot Floating Widget Integration -->
<?php
$chatbot_enabled = (isset($global_settings['chatbot_enabled']) && $global_settings['chatbot_enabled'] == '1') ? true : false;
if (!$chatbot_enabled && isset($conn)) {
    $cb_chk = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'chatbot_enabled' LIMIT 1");
    if ($cb_chk && $cb_chk->num_rows > 0) {
        $chatbot_enabled = ($cb_chk->fetch_assoc()['setting_value'] === '1');
    }
}

if ($chatbot_enabled): 
?>
    <!-- ChatBot Widget CSS & JS -->
    <link href="<?php echo ASSETS_URL; ?>/css/chatbot-widget.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/chatbot-widget.css') ? filemtime(__DIR__ . '/../assets/css/chatbot-widget.css') : '1.0'; ?>" rel="stylesheet">
    <script>
        window.sagarChatConfig = {
            apiEndpoint: '<?php echo SITE_URL; ?>/api/chatbot/chat.php'
        };
    </script>
    <script src="<?php echo ASSETS_URL; ?>/js/chatbot-widget.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/chatbot-widget.js') ? filemtime(__DIR__ . '/../assets/js/chatbot-widget.js') : '1.0'; ?>" defer></script>
<?php endif; ?>

<?php
// ── Analytics Tracker (public pages only) ────────────────────
// Never loads on admin pages. Async/deferred, failure-safe.
$_isAdminPage = (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false);
if (!$_isAdminPage):
?>
<script>window.__ssAnalytics = { baseUrl: '<?php echo defined("SITE_URL") ? SITE_URL : ""; ?>' };</script>
<script src="<?php echo ASSETS_URL; ?>/js/analytics-tracker.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/analytics-tracker.js') ? filemtime(__DIR__ . '/../assets/js/analytics-tracker.js') : '1.0'; ?>" async defer></script>
<?php endif; ?>

</body>
</html>

