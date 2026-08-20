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
    if (isset($_SESSION['user_id']) || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
        if (empty($photo)) return '';
        
        // 1. Full HTTP / HTTPS URL (e.g. Google avatar)
        if (strpos($photo, 'http://') === 0 || strpos($photo, 'https://') === 0) {
            return $photo;
        }
        
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $assets_url = defined('ASSETS_URL') ? rtrim(ASSETS_URL, '/') : ($site_url . '/assets');
        
        $clean_photo = ltrim($photo, '/');
        $basename = basename($clean_photo);
        
        // 2. Check in assets/images/
        if (file_exists($base_path . '/assets/images/' . $basename)) {
            return $assets_url . '/images/' . $basename;
        }
        if (file_exists($base_path . '/assets/images/' . $clean_photo)) {
            return $assets_url . '/images/' . $clean_photo;
        }
        
        // 3. Check in uploads/
        if (file_exists($base_path . '/uploads/media/images/' . $basename)) {
            return $site_url . '/uploads/media/images/' . $basename;
        }
        if (file_exists($base_path . '/uploads/' . $clean_photo)) {
            return $site_url . '/uploads/' . $clean_photo;
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
        
        // Static per-request cache
        static $resolve_cache = [];
        $cache_key = $img;
        if (isset($resolve_cache[$cache_key])) {
            return $resolve_cache[$cache_key];
        }
        
        $clean = ltrim($img, '/');
        $basename = basename($clean);
        
        // Strip common prefix wrappers for relative lookup
        $rel_path = $clean;
        if (strpos($rel_path, 'assets/images/') === 0) {
            $rel_path = substr($rel_path, 14);
        } elseif (strpos($rel_path, 'uploads/media/images/') === 0) {
            $rel_path = substr($rel_path, 21);
        } elseif (strpos($rel_path, 'uploads/images/') === 0) {
            $rel_path = substr($rel_path, 15);
        } elseif (strpos($rel_path, 'uploads/') === 0) {
            $rel_path = substr($rel_path, 8);
        }
        
        // 1. Check in /uploads/
        if (file_exists($base_path . '/uploads/' . $rel_path)) {
            return $resolve_cache[$cache_key] = $site_url . '/uploads/' . $rel_path;
        }
        if (file_exists($base_path . '/uploads/media/images/' . $basename)) {
            return $resolve_cache[$cache_key] = $site_url . '/uploads/media/images/' . $basename;
        }
        if (file_exists($base_path . '/uploads/images/' . $basename)) {
            return $resolve_cache[$cache_key] = $site_url . '/uploads/images/' . $basename;
        }

        // 2. Check in /assets/images/ (root and subdirectories)
        if (file_exists($base_path . '/assets/images/' . $rel_path)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/' . $rel_path;
        }
        if (file_exists($base_path . '/assets/images/' . $basename)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/' . $basename;
        }
        if (file_exists($base_path . '/assets/images/slider/' . $basename)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/slider/' . $basename;
        }
        if (file_exists($base_path . '/assets/images/features/' . $basename)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/features/' . $basename;
        }
        if (file_exists($base_path . '/assets/images/testimonials/' . $basename)) {
            return $resolve_cache[$cache_key] = $assets_url . '/images/testimonials/' . $basename;
        }
        
        // 3. Auto-heal: search for matching file with different extension or location
        $name_no_ext = pathinfo($basename, PATHINFO_FILENAME);
        if (!empty($name_no_ext)) {
            foreach (['/assets/images/', '/assets/images/slider/', '/assets/images/features/', '/uploads/media/images/', '/uploads/images/'] as $dir) {
                $matches = glob($base_path . $dir . $name_no_ext . '.*');
                if (!empty($matches)) {
                    $found_basename = basename($matches[0]);
                    $sub = trim($dir, '/');
                    return $resolve_cache[$cache_key] = $site_url . '/' . $sub . '/' . $found_basename;
                }
            }
        }
        
        // 4. Smart fallback for slides and hero banners if specific file missing
        if (strpos($basename, 'slide_') === 0 || strpos($basename, 'banner_') === 0) {
            $slider_files = glob($base_path . '/assets/images/slider/slide_*.webp');
            if (!empty($slider_files)) {
                $idx = abs(crc32($basename)) % count($slider_files);
                return $resolve_cache[$cache_key] = $assets_url . '/images/slider/' . basename($slider_files[$idx]);
            }
            $hero_files = glob($base_path . '/assets/images/hero_*.webp');
            if (!empty($hero_files)) {
                return $resolve_cache[$cache_key] = $assets_url . '/images/' . basename($hero_files[0]);
            }
        }

        // 5. Return safe fallback placeholder
        return $resolve_cache[$cache_key] = $placeholder;
    }
}

// Global Feature Icon Resolver (with smart title matching and graceful fallback)
if (!function_exists('resolve_feature_icon_url')) {
    function resolve_feature_icon_url($title, $icon_value = '') {
        $icon = trim((string)$icon_value);
        if (!empty($icon)) {
            $url = resolve_image_url($icon);
            if ($url && strpos($url, 'placeholder.svg') === false && strpos($url, 'no-image') === false) {
                return $url;
            }
        }
        
        $t = strtolower(trim((string)$title));
        $map = [
            'power saving'       => 'feature_1772177655.jpg',
            'power'              => 'feature_1772177655.jpg',
            'eco'                => 'feature_1773069037.png',
            'india'              => 'feature_1772177782.png',
            'shipping'           => 'feature_1772045426.png',
            'worldwide'          => 'feature_1772045426.png',
            'quality'            => 'feature_1772045460.png',
            'offers'             => 'feature_1772045499.png',
            'offer'              => 'feature_1772045499.png',
            'payment'            => 'feature_1772045534.png',
            'secure'             => 'feature_1772045534.png',
            'support'            => 'feature_1772176874.png',
            '24x7'               => 'feature_1772176874.png',
        ];
        
        foreach ($map as $key => $file) {
            if (strpos($t, $key) !== false) {
                $matched_url = resolve_image_url($file);
                if ($matched_url && strpos($matched_url, 'placeholder.svg') === false) {
                    return $matched_url;
                }
            }
        }
        
        return resolve_image_url($icon);
    }
}

if (!function_exists('get_feature_fallback_font_icon')) {
    function get_feature_fallback_font_icon($title) {
        $t = strtolower(trim((string)$title));
        if (strpos($t, 'power') !== false) return 'fas fa-bolt';
        if (strpos($t, 'eco') !== false) return 'fas fa-leaf';
        if (strpos($t, 'india') !== false) return 'fas fa-certificate';
        if (strpos($t, 'shipping') !== false || strpos($t, 'delivery') !== false) return 'fas fa-truck-fast';
        if (strpos($t, 'quality') !== false) return 'fas fa-award';
        if (strpos($t, 'offer') !== false) return 'fas fa-tags';
        if (strpos($t, 'payment') !== false || strpos($t, 'secure') !== false) return 'fas fa-shield-halved';
        if (strpos($t, 'support') !== false || strpos($t, '24x7') !== false) return 'fas fa-headset';
        return 'fas fa-star';
    }
}

if (!function_exists('encode_url_path')) {
    function encode_url_path($url) {
        if (empty($url)) return $url;
        $parsed = parse_url($url);
        if (isset($parsed['scheme']) && isset($parsed['host'])) {
            $scheme = $parsed['scheme'];
            $host = $parsed['host'];
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $path = isset($parsed['path']) ? $parsed['path'] : '';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            
            $pathSegments = explode('/', $path);
            $encodedSegments = array_map('rawurlencode', $pathSegments);
            $encodedPath = implode('/', $encodedSegments);
            
            return $scheme . '://' . $host . $port . $encodedPath . $query;
        }
        $pathSegments = explode('/', $url);
        $encodedSegments = array_map('rawurlencode', $pathSegments);
        return implode('/', $encodedSegments);
    }
}

// Global Product Image Resolver
if (!function_exists('resolve_product_image_url')) {
    function resolve_product_image_url($image_path, $conn = null, $product_id = null, $product_name = '') {
        $img = trim((string)$image_path);
        
        $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $assets_url = defined('ASSETS_URL') ? rtrim(ASSETS_URL, '/') : ($site_url . '/assets');
        $base_path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $placeholder_url = $assets_url . '/images/AhaConvert_3ph.webp';
        
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
                return encode_url_path($img);
            }
        }
        
        $pName = strtolower(trim((string)$product_name));

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
                    if (empty($pName)) {
                        $pName = strtolower($nRow['name'] ?? '');
                    }
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
                return encode_url_path($site_url . '/' . $clean);
            }

            // Strip directory prefixes to get bare filename
            $bare = basename($clean);

            // Check in /assets/images/ (git-tracked priority)
            if (file_exists($base_path . '/assets/images/' . $bare)) {
                return encode_url_path($assets_url . '/images/' . $bare);
            }

            // Check in /uploads/images/
            if (file_exists($base_path . '/uploads/images/' . $bare)) {
                return encode_url_path($site_url . '/uploads/images/' . $bare);
            }

            // Check in /uploads/
            if (file_exists($base_path . '/uploads/' . $bare)) {
                return encode_url_path($site_url . '/uploads/' . $bare);
            }
            
            // Auto-heal extension mismatch (e.g. db says .jpg, file is .webp)
            $name_no_ext = pathinfo($bare, PATHINFO_FILENAME);
            if (!empty($name_no_ext)) {
                $matches = glob($base_path . '/assets/images/' . $name_no_ext . '.*');
                if (!empty($matches)) {
                    return encode_url_path($assets_url . '/images/' . basename($matches[0]));
                }
                $upload_img_matches = glob($base_path . '/uploads/images/' . $name_no_ext . '.*');
                if (!empty($upload_img_matches)) {
                    return encode_url_path($site_url . '/uploads/images/' . basename($upload_img_matches[0]));
                }
                $upload_matches = glob($base_path . '/uploads/' . $name_no_ext . '.*');
                if (!empty($upload_matches)) {
                    return encode_url_path($site_url . '/uploads/' . basename($upload_matches[0]));
                }
            }
        }
        
        // Smart Keyword Auto-Matcher for Product Images (Guaranteed Web Fallbacks when disk file is missing)
        if (!empty($pName)) {
            if (strpos($pName, 'coil') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_coil pi.webp');
            }
            if (strpos($pName, 'contactor') !== false || strpos($pName, 'bno') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_bno pi.webp');
            }
            if (strpos($pName, 'float') !== false || strpos($pName, 'water level') !== false || strpos($pName, 'level controller') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_float pi.webp');
            }
            if (strpos($pName, 'software') !== false || strpos($pName, 'inventory') !== false || strpos($pName, 'stock') !== false || strpos($pName, 'billing') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_invantiry.webp');
            }
            if (strpos($pName, 'star delta') !== false || strpos($pName, 'delta') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_star delta pi.webp');
            }
            if (strpos($pName, 'oil') !== false || strpos($pName, 'oil field') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_1hp oil pi.webp');
            }
            if (strpos($pName, 'submersible') !== false || strpos($pName, 'apollo') !== false || strpos($pName, 'appolo') !== false || strpos($pName, 'pump') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_1hp appolo pi.webp');
            }
            if (strpos($pName, 'digital') !== false || strpos($pName, 'dgt') !== false) {
                return encode_url_path($assets_url . '/images/single hp dgt.webp');
            }
            if (strpos($pName, 'meter') !== false || strpos($pName, 'analog') !== false) {
                return encode_url_path($assets_url . '/images/meter gi.webp');
            }
            if (strpos($pName, 'bakelite') !== false || strpos($pName, 'connector') !== false || strpos($pName, 'pvt') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_pvt pi.webp');
            }
            if (strpos($pName, 'motor protection') !== false || strpos($pName, 'mpd') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_mpd pi.webp');
            }
            if (strpos($pName, 'push button') !== false || strpos($pName, 'button') !== false || strpos($pName, 'vastav') !== false || strpos($pName, 'gf-01') !== false || strpos($pName, 'sg') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_sg.webp');
            }
            if (strpos($pName, 'breaker') !== false || strpos($pName, 'circuit') !== false || strpos($pName, 'teknic') !== false || strpos($pName, 'pole') !== false || strpos($pName, 'mcb') !== false || strpos($pName, 'sps') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_sps.webp');
            }
            if (strpos($pName, 'stabilizer') !== false || strpos($pName, 'voltage') !== false) {
                return encode_url_path($assets_url . '/images/AhaConvert_stabilizer pi.webp');
            }
            if (strpos($pName, 'automatic') !== false || strpos($pName, 'starter') !== false || strpos($pName, 'phase') !== false || strpos($pName, '3ph') !== false || strpos($pName, 'hp') !== false) {
                if (strpos($pName, 'semi') !== false) {
                    return encode_url_path($assets_url . '/images/3 ph inner semi.webp');
                }
                return encode_url_path($assets_url . '/images/3 ph inner auto.webp');
            }
        }
        
        // Default safe fallback if file missing or image not specified
        return encode_url_path($placeholder_url);
    }
}


