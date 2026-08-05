<?php
include 'includes/header.php';

// Sensible defaults
$about_hero_title = $global_settings['about_hero_title'] ?? 'About Us';
$about_hero_subtitle = $global_settings['about_hero_subtitle'] ?? 'Learn more about our journey and values.';
$about_who_title = $global_settings['about_who_title'] ?? 'Who We Are';
$about_who_desc1 = $global_settings['about_who_desc1'] ?? 'Welcome to Sagar Starter\'s. We are dedicated to providing you the very best of products, with an emphasis on quality, customer service, and uniqueness.';
$about_who_desc2 = $global_settings['about_who_desc2'] ?? 'Founded with a passion for modern aesthetics and functional design, we have come a long way from our beginnings.';
$about_who_image = $global_settings['about_who_image'] ?? '';

// Design Settings
$c_heading_color = $global_settings['about_heading_color'] ?? '#0d6efd';
$c_heading_fs    = intval($global_settings['about_heading_font_size'] ?? 32);
$c_body_fs       = intval($global_settings['about_body_font_size'] ?? 16);
$c_icon_color    = $global_settings['about_icon_color'] ?? '#0d6efd';
$c_card_bg       = $global_settings['about_card_bg'] ?? '#ffffff';

$features = [
    [
        'icon' => $global_settings['about_f_icon1'] ?? 'fas fa-truck',
        'title' => $global_settings['about_f_title1'] ?? 'Fast Delivery',
        'desc' => $global_settings['about_f_desc1'] ?? 'We ensure your packages arrive on time, every time safely to your doorstep.'
    ],
    [
        'icon' => $global_settings['about_f_icon2'] ?? 'fas fa-hand-holding-heart',
        'title' => $global_settings['about_f_title2'] ?? 'Quality Promise',
        'desc' => $global_settings['about_f_desc2'] ?? 'Every item is carefully inspected to meet our strict quality and design standards.'
    ],
    [
        'icon' => $global_settings['about_f_icon3'] ?? 'fas fa-headset',
        'title' => $global_settings['about_f_title3'] ?? '24/7 Support',
        'desc' => $global_settings['about_f_desc3'] ?? 'Our dedicated customer service team is always here to help you when needed.'
    ]
];
?>

<?php 
$hero_bg_style = "background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);";
if (!empty($global_settings['hero_banner_about']) && file_exists(__DIR__ . '/assets/images/' . $global_settings['hero_banner_about'])) {
    $img_url = htmlspecialchars(ASSETS_URL . '/images/' . $global_settings['hero_banner_about']);
    $hero_bg_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
}
?>
<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-5" style="<?php echo $hero_bg_style; ?>">
    <div class="container py-5 text-center">
        <h1 class="display-4 fw-bold mb-3 montserrat"><?php echo htmlspecialchars($about_hero_title); ?></h1>
        <p class="lead mb-0"><?php echo htmlspecialchars($about_hero_subtitle); ?></p>
    </div>
</div>

<div class="container mb-5">
    <div class="row align-items-center mb-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <?php if (!empty($about_who_image)): ?>
                <img src="<?php echo htmlspecialchars(resolve_image_url($about_who_image)); ?>" alt="Our Team" class="img-fluid rounded-4 shadow-lg">
            <?php else: ?>
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Our Team" class="img-fluid rounded-4 shadow-lg">
            <?php endif; ?>
        </div>
        <div class="col-md-6 px-md-5">
            <h2 class="fw-bold mb-4 montserrat" style="color:<?php echo htmlspecialchars($c_heading_color); ?> !important; font-size:<?php echo $c_heading_fs; ?>px !important;"><?php echo htmlspecialchars($about_who_title); ?></h2>
            <div class="text-muted lh-lg mb-4" style="font-size:<?php echo $c_body_fs; ?>px !important;">
                <?php echo nl2br(htmlspecialchars($about_who_desc1)); ?>
            </div>
            <div class="text-muted lh-lg" style="font-size:<?php echo $c_body_fs; ?>px !important;">
                <?php echo nl2br(htmlspecialchars($about_who_desc2)); ?>
            </div>
        </div>
    </div>
    
    <div class="row text-center mt-5 pt-5">
        <?php foreach ($features as $f): ?>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4" style="background-color:<?php echo htmlspecialchars($c_card_bg); ?> !important;">
                <i class="<?php echo htmlspecialchars($f['icon']); ?> fa-3x mb-4" style="color:<?php echo htmlspecialchars($c_icon_color); ?> !important;"></i>
                <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($f['title']); ?></h4>
                <p class="text-muted" style="font-size:<?php echo $c_body_fs; ?>px !important;"><?php echo htmlspecialchars($f['desc']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
?>


