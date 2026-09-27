<?php
include 'includes/db_connect.php';

// Get the slug from URL, default to about
$slug = isset($_GET['slug']) ? $conn->real_escape_string($_GET['slug']) : 'about';

$base_url_clean = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
if (empty($base_url_clean) || strpos($base_url_clean, 'http') !== 0) {
    $base_url_clean = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.sagarstarters.com');
}

// 301 Permanent Redirects for legacy/alternate page URLs
if ($slug === 'about') {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $base_url_clean . "/about.php");
    exit;
}
if ($slug === 'contact') {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $base_url_clean . "/contact.php");
    exit;
}
if ($slug === '1772044436-f-q') {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $base_url_clean . "/page/f-q-44436");
    exit;
}
if ($slug === '1772607638-support') {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $base_url_clean . "/page/support-07638");
    exit;
}

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

// Generate Breadcrumb Schema for Static/Policy Page
require_once 'includes/SeoService.php';
$seoService = new SeoService($conn);

$base_url_clean = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
if (empty($base_url_clean) || strpos($base_url_clean, 'http') !== 0) {
    $base_url_clean = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.sagarstarters.com');
}

$breadcrumb_items = [
    ['name' => 'Home', 'url' => $base_url_clean . '/'],
    ['name' => $page['title'], 'url' => $base_url_clean . '/page/' . $slug]
];
$breadcrumb_schema = $seoService->generateBreadcrumbSchema($breadcrumb_items);

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
                <?php 
                   // Convert any inner <h1> in admin-managed content to <h2> to preserve strict single-H1 hierarchy
                   $clean_page_content = preg_replace('/<h1\b([^>]*)>/i', '<h2$1>', $page['content']);
                   $clean_page_content = preg_replace('/<\/h1>/i', '</h2>', $clean_page_content);
                   echo $clean_page_content; 
                ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
