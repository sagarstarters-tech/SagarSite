<?php
/**
 * Custom 404 Not Found Page
 */
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__);
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', BASE_PATH . '/config/');
if (!defined('DB_HOST')) require CONFIG_PATH . 'config.php';

http_response_code(404);

$site_url = defined('SITE_URL') ? SITE_URL : '';
$assets_url = defined('ASSETS_URL') ? ASSETS_URL : '/assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Page Not Found — Sagar Starter's</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .error-box { text-align: center; padding: 60px 40px; max-width: 520px; }
        .error-code { font-size: 8rem; font-weight: 900; color: #007aff; line-height: 1; }
        .error-title { font-size: 1.8rem; font-weight: 700; color: #222; margin: 16px 0 8px; }
        .error-desc { color: #666; margin-bottom: 32px; }
        .btn-home { background: #007aff; color: #fff !important; padding: 12px 32px; border-radius: 30px; text-decoration: none !important; font-weight: 600; display: inline-block; }
        .btn-home:hover { background: #005ecc; color: #fff !important; }
        .links a { color: #007aff; text-decoration: none; margin: 0 12px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-desc">The page you're looking for doesn't exist or has been moved. Let's get you back on track!</p>
        <a href="<?php echo $site_url; ?>/index.php" class="btn-home">
            <i class="fas fa-home me-2"></i>Go to Homepage
        </a>
        <div class="links mt-4">
            <a href="<?php echo $site_url; ?>/shop.php"><i class="fas fa-store me-1"></i>Shop</a>
            <a href="<?php echo $site_url; ?>/contact.php"><i class="fas fa-envelope me-1"></i>Contact</a>
            <a href="javascript:history.back()"><i class="fas fa-arrow-left me-1"></i>Go Back</a>
        </div>
    </div>
</body>
</html>
