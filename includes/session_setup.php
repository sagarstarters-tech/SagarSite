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
    
    // 4. Set browser cache revalidation strategy
    if (isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
    } else {
        header("Cache-Control: no-cache, must-revalidate");
    }
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

// Global Image Resolver (Generic for Banners, Slides, Categories, Features, etc.)
if (!function_exists('resolve_image_url')) {
    function resolve_image_url($image_path, $default_fallback = '') {
        $img = trim((string)$image_path);
        
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $assets_url = defined('ASSETS_URL') ? rtrim(ASSETS_URL, '/') : ($site_url . '/assets');
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $placeholder = !empty($default_fallback) ? $default_fallback : ($assets_url . '/images/placeholder.svg');
        
        $dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];
        if (empty($img) || in_array(strtolower(basename($img)), $dummies)) {
            return $placeholder;
        }
        
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
            return $img;
        }
        
        // Static per-request cache to avoid redundant disk I/O (glob is expensive)
        static $resolve_cache = [];
        $cache_key = $img;
        if (isset($resolve_cache[$cache_key])) {
            return $resolve_cache[$cache_key];
        }
        
        $clean = ltrim($img, '/');
        if (strpos($clean, 'assets/images/') === 0) {
            $clean = substr($clean, 14);
        } elseif (strpos($clean, 'uploads/images/') === 0) {
            $clean = substr($clean, 15);
        } elseif (strpos($clean, 'uploads/') === 0) {
            $clean = substr($clean, 8);
        }
        
        // 1. Check in /uploads/
        if (file_exists($base_path . '/uploads/' . $clean)) {
            return $resolve_cache[$cache_key] = $site_url . '/uploads/' . $clean;
        }
        
        // 2. Check in /assets/images/
        if (file_exists($base_path . '/assets/images/' . $clean)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/' . $clean;
        }
        
        // 3. Auto-heal: search for matching file with different extension (e.g. .webp instead of .jpg)
        $name_no_ext = pathinfo($clean, PATHINFO_FILENAME);
        if (!empty($name_no_ext)) {
            $matches = glob($base_path . '/assets/images/' . $name_no_ext . '.*');
            if (!empty($matches)) {
                return $resolve_cache[$cache_key] = $assets_url . '/images/' . basename($matches[0]);
            }
            $upload_matches = glob($base_path . '/uploads/' . $name_no_ext . '.*');
            if (!empty($upload_matches)) {
                return $resolve_cache[$cache_key] = $site_url . '/uploads/' . basename($upload_matches[0]);
            }
        }
        
        // 4. Smart fallback for slides and banners if specific file missing
        if (strpos($clean, 'slide_') === 0 || strpos($clean, 'banner_') === 0) {
            $banner_files = glob($base_path . '/assets/images/banner_*.webp');
            if (!empty($banner_files)) {
                $idx = abs(crc32($clean)) % count($banner_files);
                return $resolve_cache[$cache_key] = $assets_url . '/images/' . basename($banner_files[$idx]);
            }
            $hero_files = glob($base_path . '/assets/images/hero_*.webp');
            if (!empty($hero_files)) {
                return $resolve_cache[$cache_key] = $assets_url . '/images/' . basename($hero_files[0]);
            }
        }

        // 5. Return safe fallback placeholder instead of broken 404 URL
        return $resolve_cache[$cache_key] = $placeholder;
    }
}

// Global Product Image Resolver
if (!function_exists('resolve_product_image_url')) {
    function resolve_product_image_url($image_path, $conn = null, $product_id = null) {
        $img = trim((string)$image_path);
        
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $assets_url = defined('ASSETS_URL') ? rtrim(ASSETS_URL, '/') : ($site_url . '/assets');
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $placeholder_url = $assets_url . '/images/logo.jpg';
        
        // Filter out dummy/empty/invalid image strings
        $dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];
        if (empty($img) || in_array(strtolower(basename($img)), $dummies)) {
            $img = '';
        }

        // If $img is a full HTTP URL pointing to our domain, convert to relative path for disk verification
        if (!empty($img) && (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0)) {
            $site_host = parse_url($site_url, PHP_URL_HOST);
            $img_host = parse_url($img, PHP_URL_HOST);
            if (!empty($site_host) && !empty($img_host) && strtolower($site_host) === strtolower($img_host)) {
                $imgPath = parse_url($img, PHP_URL_PATH);
                $img = ltrim((string)$imgPath, '/');
            } else {
                // External image URL (different domain) - return directly
                return $img;
            }
        }
        
        $pName = '';

        // Load name & alternative image from database if product_id is provided
        if (!empty($product_id)) {
            $p_id = intval($product_id);
            
            // 1. Try via mysqli $conn if passed or available in GLOBALS
            if ($conn === null && isset($GLOBALS['conn'])) {
                $conn = $GLOBALS['conn'];
            }
            if ($conn !== null && $conn instanceof \mysqli) {
                $nq = $conn->query("SELECT image, name FROM products WHERE id = $p_id");
                if ($nq && $nRow = $nq->fetch_assoc()) {
                    $pName = strtolower($nRow['name'] ?? '');
                    if (empty($img)) {
                        $pImg = trim($nRow['image'] ?? '');
                        if (!empty($pImg) && !in_array(strtolower(basename($pImg)), $dummies)) {
                            $img = $pImg;
                        }
                    }
                }
                if (empty($img)) {
                    $gq = $conn->query("SELECT image FROM product_images WHERE product_id = $p_id ORDER BY position ASC, id ASC");
                    if ($gq) {
                        while ($gRow = $gq->fetch_assoc()) {
                            $gImg = trim($gRow['image'] ?? '');
                            if (!empty($gImg) && !in_array(strtolower(basename($gImg)), $dummies)) {
                                $img = $gImg;
                                break;
                            }
                        }
                    }
                }
            }
            
            // 2. Try via PDO DbConnection
            if (empty($pName) || empty($img)) {
                try {
                    if (file_exists($base_path . '/config/DbConnection.php')) {
                        require_once $base_path . '/config/DbConnection.php';
                        $pdoInst = \DbConnection::getInstance();
                        
                        if (empty($pName)) {
                            $stmtP = $pdoInst->prepare("SELECT image, name FROM products WHERE id = ?");
                            $stmtP->execute([$p_id]);
                            $pRow = $stmtP->fetch(\PDO::FETCH_ASSOC);
                            if ($pRow) {
                                $pName = strtolower($pRow['name'] ?? '');
                                if (empty($img)) {
                                    $pImg = trim($pRow['image'] ?? '');
                                    if (!empty($pImg) && !in_array(strtolower(basename($pImg)), $dummies)) {
                                        $img = $pImg;
                                    }
                                }
                            }
                        }

                        if (empty($img)) {
                            $stmtG = $pdoInst->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC");
                            $stmtG->execute([$p_id]);
                            while ($gRow = $stmtG->fetch(\PDO::FETCH_ASSOC)) {
                                $gImg = trim($gRow['image'] ?? '');
                                if (!empty($gImg) && !in_array(strtolower(basename($gImg)), $dummies)) {
                                    $img = $gImg;
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Test relative image path on disk
        if (!empty($img)) {
            $clean = ltrim(str_replace('\\', '/', $img), '/');
            
            // Direct relative path check from root
            if (file_exists($base_path . '/' . $clean)) {
                return $site_url . '/' . $clean;
            }

            // Strip directory prefixes to get bare filename
            $bare = basename($clean);

            // Check in /assets/images/ (git-tracked priority)
            if (file_exists($base_path . '/assets/images/' . $bare)) {
                return $assets_url . '/images/' . $bare;
            }

            // Check in /uploads/images/
            if (file_exists($base_path . '/uploads/images/' . $bare)) {
                return $site_url . '/uploads/images/' . $bare;
            }

            // Check in /uploads/
            if (file_exists($base_path . '/uploads/' . $bare)) {
                return $site_url . '/uploads/' . $bare;
            }
            
            // Auto-heal extension mismatch (e.g. db says .jpg, file is .webp)
            $name_no_ext = pathinfo($bare, PATHINFO_FILENAME);
            if (!empty($name_no_ext)) {
                $matches = glob($base_path . '/assets/images/' . $name_no_ext . '.*');
                if (!empty($matches)) {
                    return $assets_url . '/images/' . basename($matches[0]);
                }
                $upload_img_matches = glob($base_path . '/uploads/images/' . $name_no_ext . '.*');
                if (!empty($upload_img_matches)) {
                    return $site_url . '/uploads/images/' . basename($upload_img_matches[0]);
                }
                $upload_matches = glob($base_path . '/uploads/' . $name_no_ext . '.*');
                if (!empty($upload_matches)) {
                    return $site_url . '/uploads/' . basename($upload_matches[0]);
                }
            }
        }
        
        // Smart Keyword Auto-Matcher for Product Images (Guaranteed Web Fallbacks when disk file is missing)
        if (!empty($pName)) {
            if (strpos($pName, 'push button') !== false || strpos($pName, 'button') !== false || strpos($pName, 'vastav') !== false || strpos($pName, 'gf-01') !== false) {
                return $assets_url . '/images/AhaConvert_sg.webp';
            }
            if (strpos($pName, 'breaker') !== false || strpos($pName, 'circuit') !== false || strpos($pName, 'teknic') !== false || strpos($pName, 'pole') !== false || strpos($pName, '20a') !== false || strpos($pName, '16a') !== false || strpos($pName, '250vac') !== false || strpos($pName, 'mcb') !== false) {
                return $assets_url . '/images/AhaConvert_sps.webp';
            }
            if (strpos($pName, 'star delta') !== false || strpos($pName, 'delta') !== false) {
                return $assets_url . '/images/AhaConvert_star delta pi.webp';
            }
            if (strpos($pName, 'submersible') !== false || strpos($pName, 'apollo') !== false || strpos($pName, 'appolo') !== false || strpos($pName, 'pump') !== false) {
                return $assets_url . '/images/AhaConvert_1hp appolo pi.webp';
            }
            if (strpos($pName, 'oil') !== false || strpos($pName, 'oil field') !== false) {
                return $assets_url . '/images/AhaConvert_1hp oil pi.webp';
            }
            if (strpos($pName, 'stabilizer') !== false || strpos($pName, 'voltage') !== false) {
                return $assets_url . '/images/AhaConvert_stabilizer pi.webp';
            }
            if (strpos($pName, 'switch') !== false || strpos($pName, 'contactor') !== false || strpos($pName, 'bno') !== false) {
                return $assets_url . '/images/AhaConvert_bno pi.webp';
            }
            if (strpos($pName, 'float') !== false) {
                return $assets_url . '/images/float-1.webp';
            }
            if (strpos($pName, 'digital') !== false || strpos($pName, 'meter') !== false || strpos($pName, 'dgt') !== false) {
                return $assets_url . '/images/AhaConvert_single hp dgt.webp';
            }
        }
        
        // Final Fallback: if string was provided but not found on disk, attempt direct path or placeholder
        if (!empty($img)) {
            $clean = ltrim(str_replace('\\', '/', $img), '/');
            if (strpos($clean, 'uploads/') === 0) {
                return $site_url . '/' . $clean;
            }
            $bare = basename($clean);
            return $site_url . '/uploads/images/' . $bare;
        }

        // Default safe fallback (Sagar Starters Logo) if file missing
        return $placeholder_url;
    }
}


