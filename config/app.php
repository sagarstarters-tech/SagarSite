<?php
/**
 * ============================================================
 *  APPLICATION CONFIGURATION
 *  Location: /config/app.php
 * ============================================================
 *  Non-secret application settings. URL values are driven by
 *  SITE_URL in .env or auto-detected domain on live servers.
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    exit('No direct script access allowed');
}

$_env_site_url = rtrim(_env('SITE_URL', ''), '/');

// Dynamic resolution: if running on a live web server (non-localhost HTTP_HOST)
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    $protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'];
    
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = preg_replace('#/(admin|api|user|auth)(/.*)?$#', '', $script_dir);
    $subfolder  = ($script_dir === '/' || $script_dir === '\\') ? '' : rtrim($script_dir, '/');
    
    $_site_url = "$protocol://$host" . $subfolder;
} else {
    $_site_url = $_env_site_url;
}

return [
    // ── URLs ──────────────────────────────────────────────
    'site_url'       => $_site_url,
    'assets_url'     => $_site_url . '/assets',
    'store_base_url' => $_site_url . '/',
    'admin_base_url' => $_site_url . '/admin/',

    // ── SMTP ──────────────────────────────────────────────
    'smtp_host'      => _env('SMTP_HOST', 'smtp.gmail.com'),
    'smtp_user'      => _env('SMTP_USER', 'sagarstarters@gmail.com'),
    'smtp_pass'      => _env('SMTP_PASS', 'wbgi uyxd bnsk kaqm'),
    'smtp_port'      => (int) _env('SMTP_PORT', '465'),
    'smtp_secure'    => _env('SMTP_SECURE', 'ssl'),
    'mail_from_name' => _env('MAIL_FROM_NAME', "Sagar Starter's Support"),
];
