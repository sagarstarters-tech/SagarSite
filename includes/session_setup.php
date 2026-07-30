<?php
/**
 * Global Session Setup
 * Ensures consistent session behavior and domain-agnostic cookies.
 */

if (session_status() === PHP_SESSION_NONE) {
    // 1. Use a unique session name to avoid conflicts with other sites on shared hosting
    session_name('SAGAR_STORE_SESSION');

    // 2. Set strict but shared cookie parameters
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '86400'); // 24 hours
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Find the root domain (e.g., sagarstarters.com)
    $domain = $host;
    // Strip port if exists
    if (($pos = strpos($domain, ':')) !== false) {
        $domain = substr($domain, 0, $pos);
    }

    if (strpos($domain, 'sagarstarters.com') !== false) {
        $domain = 'sagarstarters.com';
    } else {
        $domain = preg_replace('/^www\./i', '', $domain);
    }
    
    // 3. Security & HTTPS detection
    $is_https = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    $cookie_domain = ($domain !== 'localhost' && !filter_var($domain, FILTER_VALIDATE_IP)) ? '.' . $domain : '';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path' => '/',
            'domain' => $cookie_domain,
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400 * 30, '/; samesite=Lax', $cookie_domain, $is_https, true);
    }
    
    session_start();
    
    // 4. Force browser to revalidate so Home page UI changes after login/logout
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// 5. Global CSRF Protection Generation (outside status check for pre-started sessions)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF Helpers
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
    }
}
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Global Profile Photo Resolver
if (!function_exists('resolve_profile_photo_url')) {
    function resolve_profile_photo_url($photo, $role = '') {
        $photo = trim((string)$photo);
        
        // 1. Full HTTP / HTTPS URL (e.g. Google avatar)
        if (strpos($photo, 'http://') === 0 || strpos($photo, 'https://') === 0) {
            return $photo;
        }
        
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        
        // Derive clean relative site URL (e.g. "" or "/SagarSite") for 100% domain & protocol independence
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $clean_site_url = preg_replace('#^https?://[^/]+#i', '', $site_url);
        
        // 2. Check if specific filename exists in assets/images
        if (!empty($photo)) {
            $clean_photo = ltrim($photo, '/');
            if (strpos($clean_photo, 'assets/images/') === 0) {
                $clean_photo = substr($clean_photo, 14);
            }
            if (file_exists($base_path . '/assets/images/' . $clean_photo)) {
                return $clean_site_url . '/assets/images/' . $clean_photo;
            }
        }
        
        // 3. Fallback to default profile photo if available
        if (file_exists($base_path . '/assets/images/profile_69c14f73c250a.png')) {
            return $clean_site_url . '/assets/images/profile_69c14f73c250a.png';
        }
        
        return '';
    }
}

// Global Product Image Resolver
if (!function_exists('resolve_product_image_url')) {
    function resolve_product_image_url($image_path, $conn = null, $product_id = null) {
        $img = trim((string)$image_path);
        
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $clean_site_url = preg_replace('#^https?://[^/]+#i', '', $site_url);
        $placeholder_url = $clean_site_url . '/assets/images/placeholder.svg';
        
        // Filter out dummy/empty/invalid image strings
        $dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];
        if (empty($img) || in_array(strtolower(basename($img)), $dummies)) {
            $img = '';
        }
        
        // If main image is empty, scan ALL gallery images for product_id until a valid existing image file is found
        if (empty($img) && $conn !== null && !empty($product_id)) {
            $p_id = intval($product_id);
            $gal_q = $conn->query("SELECT image FROM product_images WHERE product_id = $p_id ORDER BY position ASC, id ASC");
            if ($gal_q && $gal_q->num_rows > 0) {
                while ($g_row = $gal_q->fetch_assoc()) {
                    $g_img = trim($g_row['image'] ?? '');
                    if (empty($g_img) || in_array(strtolower(basename($g_img)), $dummies)) {
                        continue;
                    }
                    $check_clean = ltrim($g_img, '/');
                    if (strpos($check_clean, 'assets/images/') === 0) {
                        $check_clean = substr($check_clean, 14);
                    }
                    if (file_exists($base_path . '/assets/images/' . $check_clean) || file_exists($base_path . '/uploads/' . $check_clean)) {
                        $img = $g_img;
                        break; // Found first real photo in gallery!
                    }
                }
            }
        }
        
        if (empty($img)) {
            return $placeholder_url;
        }
        
        // 1. Full HTTP / HTTPS URL
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
            return $img;
        }
        
        // Clean leading slashes and redundant directory prefixes
        $clean = ltrim($img, '/');
        if (strpos($clean, 'assets/images/') === 0) {
            $clean = substr($clean, 14);
        } elseif (strpos($clean, 'uploads/images/') === 0) {
            $clean = substr($clean, 15);
        } elseif (strpos($clean, 'uploads/') === 0) {
            $clean_sub = substr($clean, 8);
            if (file_exists($base_path . '/assets/images/' . $clean_sub)) {
                $clean = $clean_sub;
            }
        }
        
        // 2. Check if file exists in assets/images/
        if (file_exists($base_path . '/assets/images/' . $clean)) {
            return $clean_site_url . '/assets/images/' . $clean;
        }
        
        // 3. Check if file exists in uploads/
        if (file_exists($base_path . '/uploads/' . $clean)) {
            return $clean_site_url . '/uploads/' . $clean;
        }
        
        // Default fallback to placeholder SVG vector if file is missing on disk
        return $placeholder_url;
    }
}


