<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';

// Check admin auth since we put this before admin_header
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

// Default keys we support for site content
$site_keys = [
    'footer_text' => 'Enter a short about us snippet for the footer column.',
    'contact_address' => '123 Main Street, City, Country',
    'contact_phone' => '+1 (555) 123-4567',
    'contact_email' => 'support@yourstore.com',
    'header_announcement' => 'Welcome to our store! Free shipping on orders over $50.',
    'social_facebook' => 'https://facebook.com/yourpage',
    'social_twitter' => 'https://twitter.com/yourhandle',
    'social_instagram' => 'https://instagram.com/yourhandle',
    'social_linkedin' => 'https://linkedin.com/company/yourcompany',
    'footer_copyright' => 'Copyright &copy; ' . date("Y") . ' Sagar Starter\'s. Powered by Sagar Starter\'s.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($site_keys as $key => $default) {
        if (isset($_POST[$key])) {
            $value = $conn->real_escape_string($_POST[$key]);
            
            // Check if key exists
            $check = $conn->query("SELECT setting_key FROM settings WHERE setting_key = '$key'");
            if ($check && $check->num_rows > 0) {
                // Update
                $conn->query("UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
            } else {
                // Insert
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
            }
        }
    }
    
    header("Location: manage_site_content.php?success=Site content updated successfully");
    exit;
}

include 'admin_header.php';

// Fetch all current settings into a flat array
$current_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="h3 mb-0 text-gray-800">Footer &amp; Global Content</h2>
    <a href="manage_homepage.php" class="btn btn-primary btn-sm">
        <i class="fas fa-desktop me-1"></i>Manage Homepage Sections &amp; UI
    </a>
</div>

<?php if (isset($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
    </div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="manage_site_content.php">
    <?php echo csrf_input(); ?>
    <div class="row">
        
        <!-- Left Column -->
        <div class="col-md-6 mb-4">
            
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-bullhorn me-2"></i>Global Elements</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Header Top-Bar Announcement</label>
                        <input type="text" name="header_announcement" class="form-control form-control-lg bg-light" 
                               value="<?php echo htmlspecialchars($current_settings['header_announcement'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['header_announcement']); ?>">
                        <div class="form-text">Displayed at the very top of every page.</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-map-marker-alt me-2"></i>Contact Information</h6>
                    <a href="manage_contact.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Manage Here
                    </a>
                </div>
                <div class="card-body py-3">
                    <div class="alert alert-info mb-0 py-2 rounded-3" style="font-size:0.92rem;">
                        <i class="fas fa-info-circle me-2"></i>
                        Contact Address, Phone Number, and Email are now managed in
                        <a href="manage_contact.php" class="fw-bold alert-link">Frontend Content → Contact Us Page</a>.
                    </div>
                </div>
            </div>
            

        </div>
        
        <!-- Right Column -->
        <div class="col-md-6 mb-4">
            
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-align-left me-2"></i>Footer Boilerplate</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Footer Company Bio</label>
                        <textarea name="footer_text" class="form-control bg-light" rows="4" 
                                  placeholder="<?php echo htmlspecialchars($site_keys['footer_text']); ?>"><?php echo htmlspecialchars($current_settings['footer_text'] ?? ''); ?></textarea>
                        <div class="form-text">This is the text that appears under the logo in the bottom left footer column.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Footer Copyright Text</label>
                        <input type="text" name="footer_copyright" class="form-control bg-light" 
                               value="<?php echo htmlspecialchars($current_settings['footer_copyright'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['footer_copyright']); ?>">
                        <div class="form-text">Visible at the very bottom of the page.</div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-share-alt me-2"></i>Social Media Links</h6>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="fab fa-facebook-f text-primary" style="width: 20px;"></i></span>
                        <input type="text" name="social_facebook" class="form-control bg-light border-0" 
                               value="<?php echo htmlspecialchars($current_settings['social_facebook'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['social_facebook']); ?>">
                    </div>
                    
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="fab fa-twitter text-info" style="width: 20px;"></i></span>
                        <input type="text" name="social_twitter" class="form-control bg-light border-0" 
                               value="<?php echo htmlspecialchars($current_settings['social_twitter'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['social_twitter']); ?>">
                    </div>
                    
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="fab fa-instagram text-danger" style="width: 20px;"></i></span>
                        <input type="text" name="social_instagram" class="form-control bg-light border-0" 
                               value="<?php echo htmlspecialchars($current_settings['social_instagram'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['social_instagram']); ?>">
                    </div>
                    
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="fab fa-linkedin-in text-primary" style="width: 20px;"></i></span>
                        <input type="text" name="social_linkedin" class="form-control bg-light border-0" 
                               value="<?php echo htmlspecialchars($current_settings['social_linkedin'] ?? ''); ?>" 
                               placeholder="<?php echo htmlspecialchars($site_keys['social_linkedin']); ?>">
                    </div>
                    
                    <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Leave a link completely blank to hide its icon from the footer.</div>
                </div>
            </div>
            
            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary btn-lg btn-custom px-5"><i class="fas fa-save me-2"></i>Save All Changes</button>
            </div>
            
        </div>
    </div>
</form>

<?php include 'admin_footer.php'; ?>
