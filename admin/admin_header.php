<?php
/**
 * Admin Header (Modular Version)
 */

ob_start();

include_once __DIR__ . '/../includes/session_setup.php';

// 1. Database & Global Settings
include_once __DIR__ . '/../includes/db_connect.php';

// 2. Auth Check
require_once __DIR__ . '/core/AuthMiddleware.php';
AuthMiddleware::check($conn);

// 3. Helper Functions
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/flash.php';
require_once __DIR__ . '/helpers/url.php';

// 4. Current Page
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);

// 5. Load Menu Config
$__admin_menu = require __DIR__ . '/config/menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's"); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/admin-sidebar.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/admin-responsive.css" rel="stylesheet">
    <?php
    require_once __DIR__ . '/../includes/ThemeService.php';
    ThemeService::injectCSS($conn);
    ?>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php
    // Dynamic header scripts
    require_once __DIR__ . '/../includes/ScriptService.php';
    $scriptService = new ScriptService($conn);
    echo $scriptService->getHeaderScripts();
    ?>
    <!-- Auto Contrast Algorithm -->
    <script>
        window.siteConfig = {
            autoContrast: <?php echo (isset($global_settings['auto_text_contrast']) && $global_settings['auto_text_contrast'] == '1') ? 'true' : 'false'; ?>
        };
    </script>
    <script src="<?php echo ASSETS_URL; ?>/js/auto-contrast.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/auto-contrast.js') ? filemtime(__DIR__ . '/../assets/js/auto-contrast.js') : '2.0'; ?>" defer></script>
</head>
<body class="bg-light">

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<?php if ($current_page !== 'admin_login.php'): ?>
<div class="container-fluid p-0 admin-main-wrapper">
    <div class="row g-0">

        <!-- Sidebar -->
        <div class="admin-sidebar p-0" id="adminSidebar">
            <!-- Logo -->
            <div class="admin-sidebar-logo text-center">
                <?php
                $admin_logo = $global_settings['header_logo_image'] ?? 'logo.jpg';
                if (!file_exists(BASE_PATH . '/assets/images/' . $admin_logo)) {
                    $admin_logo = file_exists(BASE_PATH . '/assets/images/logo.jpg') ? 'logo.jpg' : 'logo_1772118384.jpeg';
                }
                ?>
                <img src="<?php echo ASSETS_URL; ?>/images/<?php echo htmlspecialchars($admin_logo); ?>"
                     alt="Logo"
                     class="img-fluid"
                     style="height:<?php echo htmlspecialchars($global_settings['header_logo_height'] ?? '45'); ?>px; width:auto; object-fit:contain;">
            </div>

            <!-- Menu -->
            <?php
            $menu = $__admin_menu;
            require __DIR__ . '/views/partials/sidebar.php';
            ?>
        </div>

        <!-- Main Content -->
        <div class="admin-main-col" id="adminMainCol">

            <!-- Topbar -->
            <div class="admin-topbar shadow-sm">
                <div class="d-flex align-items-center w-100">
                    <button class="sidebar-toggle-btn me-3" id="sidebarToggle" type="button">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="d-flex align-items-center justify-content-between flex-grow-1">
                        <div>
                            <span class="fw-bold fs-5 d-inline-block">
                                <?php
                                $page_label = str_replace(['manage_', '-', '_', '.php'], ['', ' ', ' ', ''], $current_page);
                                echo ucwords(trim($page_label));
                                ?>
                            </span>
                            <div class="admin-breadcrumb d-none d-sm-block">
                                <a href="<?php echo ADMIN_BASE_URL; ?>index.php" class="text-decoration-none text-muted small">Admin</a>
                                <span class="mx-1 small">/</span>
                                <span class="small"><?php echo ucwords(trim($page_label)); ?></span>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-semibold text-muted d-none d-md-inline small">
                                <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>
                            </span>
                            <div class="dropdown">
                                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle-nocaret" data-mdb-toggle="dropdown">
                                     <?php 
                                     $profile_pic_url = resolve_profile_photo_url($_SESSION['profile_photo'] ?? '', $_SESSION['role'] ?? '');
                                     ?>
                                     <?php if (!empty($profile_pic_url)): ?>
                                         <img src="<?php echo htmlspecialchars($profile_pic_url); ?>"
                                              alt="Profile"
                                              class="rounded-circle shadow-sm"
                                              style="width:36px;height:36px;object-fit:cover;border:2px solid #fff;"
                                              onerror="this.outerHTML='<i class=\'fas fa-user-circle fa-2x text-muted\'></i>';">
                                     <?php else: ?>
                                         <i class="fas fa-user-circle fa-2x text-muted"></i>
                                     <?php endif; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                                    <li><a class="dropdown-item py-2" href="<?php echo ADMIN_BASE_URL; ?>manage_settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="<?php echo STORE_BASE_URL; ?>includes/auth.php?action=logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Content Wrapper -->
            <div class="admin-content">
            <?php if (function_exists('render_flash')) render_flash(); ?>
<?php endif; ?>
