<?php
include 'includes/db_connect.php';

// Get the slug from URL, default to about
$slug = isset($_GET['slug']) ? $conn->real_escape_string($_GET['slug']) : 'about';

// Fetch the page content
$query = "SELECT * FROM pages WHERE slug = '$slug'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $page = $result->fetch_assoc();
    $page_meta_title = $page['meta_title'] ?? $page['title'];
    $page_meta_description = $page['meta_description'] ?? '';
} else {
    // Return 404 if not found
    header("HTTP/1.0 404 Not Found");
    $page = [
        'title' => 'Page Not Found',
        'content' => '<div class="text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                        <h2 class="fw-bold">404 - Page Not Found</h2>
                        <p class="text-muted">The page you are looking for does not exist or has been moved.</p>
                        <a href="index.php" class="btn btn-primary btn-custom mt-3">Return to Home</a>
                      </div>'
    ];
    $page_meta_title = '404 Not Found';
    $page_meta_description = '';
}

include 'includes/header.php';
?>

<?php 
$hero_bg_style = "background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);";
$setting_key = 'hero_banner_policy'; // Default fallback for generic pages

$slug_lower = strtolower($slug);
$title_lower = isset($page['title']) ? strtolower($page['title']) : '';

// if the slug or title implies a specific page type matching our settings:
if (strpos($slug_lower, 'support') !== false || strpos($title_lower, 'support') !== false) {
    $setting_key = 'hero_banner_support';
} elseif (strpos($slug_lower, 'faq') !== false || strpos($slug_lower, 'f-q') !== false || strpos($title_lower, 'faq') !== false || strpos($title_lower, 'f&q') !== false || strpos($title_lower, 'f & q') !== false) {
    $setting_key = 'hero_banner_faq';
}

if (!empty($global_settings[$setting_key])) {
    $img_url = htmlspecialchars(resolve_image_url($global_settings[$setting_key]));
    if (!empty($img_url) && strpos($img_url, 'placeholder') === false) {
        $hero_bg_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
    }
}
?>
<!-- Hero Section for custom pages -->
<div class="bg-primary text-white py-5 mb-5" style="<?php echo $hero_bg_style; ?>">
    <div class="container py-5 text-center">
        <h1 class="display-4 fw-bold mb-3 montserrat"><?php echo htmlspecialchars($page['title']); ?></h1>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="page-content bg-white p-4 p-md-5 rounded-4 shadow-sm">
                <!-- 
                   We are outputting raw HTML content here since it is managed by the admin.
                   In a real-world scenario, you might want to sanitize this using a library like HTMLPurifier 
                   if editors are untrusted. 
                -->
                <?php echo $page['content']; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
