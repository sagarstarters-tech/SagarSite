<?php
/**
 * WAPI SaaS - Dynamic Header
 * Included on all public pages
 */
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/session.php';
}

$settings = new Settings();
$siteName = $settings->get('site_name', 'WAPI');
$siteLogo = $settings->get('site_logo', '');
$primaryColor = $settings->get('primary_color', '#6c63ff');
$recaptchaSiteKey = $settings->get('recaptcha_site_key', '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription ?? $settings->get('site_description', '')); ?>">
    <meta name="keywords" content="<?= e($settings->get('meta_keywords', '')); ?>">
    <meta name="author" content="<?= e($siteName); ?>">
    <?= CSRF::metaTag(); ?>

    <title><?= e(($pageTitle ?? 'Home') . ' | ' . $siteName); ?></title>
    
    <!-- Favicon -->
    <?php 
    $siteFavicon = $settings->get('site_favicon', 'assets/images/favicon.png');
    $siteFaviconPath = str_replace('/wapi/', '', $siteFavicon);
    $siteFaviconUrl = (strpos($siteFaviconPath, 'http') === 0) ? $siteFaviconPath : baseUrl($siteFaviconPath);
    ?>
    <link rel="icon" href="<?= e($siteFaviconUrl); ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?= $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Dynamic Theme Colors -->
    <style>
        :root {
            --primary: <?= e($primaryColor); ?>;
        }
    </style>
    
    <!-- PWA -->
    <link rel="manifest" href="<?= baseUrl('manifest.json'); ?>">
    <meta name="theme-color" content="<?= e($primaryColor); ?>">
    
    <?php 
    if ($recaptchaSiteKey) {
        echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
    
    // Auto-hide public nav for admin and dashboard pages
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false) {
        $hideNav = true;
    }
    ?>
</head>
<body class="<?= (isset($hideNav) && $hideNav) ? 'hide-nav' : ''; ?>">
    <?php if (!isset($hideNav) || !$hideNav): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" id="mainNav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= baseUrl(); ?>">
                <?php 
                if ($siteLogo): 
                    $logoPath = str_replace('/wapi/', '', $siteLogo);
                    $logoUrl = (strpos($logoPath, 'http') === 0) ? $logoPath : baseUrl($logoPath);
                ?>
                    <img src="<?= e($logoUrl); ?>" alt="<?= e($siteName); ?>" style="max-height: 48px;">
                <?php endif; ?>
                <span class="brand"><?= e($siteName); ?></span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.5rem; color: var(--text-primary);"></i>
            </button>

            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                    <li class="nav-item"><a class="nav-link" href="<?= baseUrl('#features'); ?>">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= baseUrl('#pricing'); ?>">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= baseUrl('#demo'); ?>">Demo</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= baseUrl('#faq'); ?>">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= baseUrl('docs/'); ?>">API Docs</a></li>
                    <li class="nav-item ms-lg-2">
                        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
                            <i class="bi bi-moon-fill"></i>
                        </button>
                    </li>
                    <?php if (Auth::isLoggedIn()): ?>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-primary btn-sm" href="<?= baseUrl((Auth::isAdmin() ? 'admin' : 'dashboard') . '/'); ?>">
                                <i class="bi bi-grid-fill"></i> Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-1">
                            <a class="nav-link" href="<?= baseUrl('auth/login.php'); ?>">Login</a>
                        </li>
                        <li class="nav-item ms-lg-1">
                            <a class="btn btn-primary btn-sm" href="<?= baseUrl('auth/register.php'); ?>">
                                Get Started <i class="bi bi-arrow-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
