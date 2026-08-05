<?php
/**
 * Dynamic OpenGraph Image Converter / Proxy
 * Converts WebP product images on-the-fly to JPEG for Facebook, WhatsApp & Twitter previews.
 * Facebook OG crawler does not support WebP images in og:image tags.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

$raw_img = $_GET['img'] ?? '';
$raw_img = ltrim(trim($raw_img), '/');

// Security check: prevent directory traversal
if (empty($raw_img) || strpos($raw_img, '..') !== false) {
    serve_default();
}

$file_path = BASE_PATH . '/' . $raw_img;

if (!file_exists($file_path) || !is_file($file_path)) {
    // Try in assets/images/ as fallback
    $alt_path = BASE_PATH . '/assets/images/' . basename($raw_img);
    if (file_exists($alt_path)) {
        $file_path = $alt_path;
    } else {
        serve_default();
    }
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// If already JPEG, PNG, or GIF, serve directly
if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
    $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
    send_image_headers($mime, filesize($file_path), filemtime($file_path));
    readfile($file_path);
    exit;
}

// If WebP, convert to JPEG for Facebook compatibility
if ($ext === 'webp') {
    $cache_dir = BASE_PATH . '/uploads/cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    $cache_filename = 'og_' . md5($file_path . '_' . filemtime($file_path)) . '.jpg';
    $cache_path = $cache_dir . '/' . $cache_filename;
    
    // Serve cached JPEG if valid
    if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($file_path)) {
        send_image_headers('image/jpeg', filesize($cache_path), filemtime($cache_path));
        readfile($cache_path);
        exit;
    }
    
    // Convert WebP to JPEG using GD if available
    if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
        $im = @imagecreatefromwebp($file_path);
        if ($im) {
            // If image is transparent, create white canvas
            $width = imagesx($im);
            $height = imagesy($im);
            $bg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $im, 0, 0, 0, 0, $width, $height);
            imagedestroy($im);
            
            if (@imagejpeg($bg, $cache_path, 90)) {
                imagedestroy($bg);
                send_image_headers('image/jpeg', filesize($cache_path), filemtime($cache_path));
                readfile($cache_path);
                exit;
            }
            imagedestroy($bg);
        }
    }
    
    // Fallback: if conversion fails, serve original webp
    send_image_headers('image/webp', filesize($file_path), filemtime($file_path));
    readfile($file_path);
    exit;
}

serve_default();

function send_image_headers($mime, $size, $mtime) {
    header("Content-Type: $mime");
    header("Content-Length: $size");
    header("Cache-Control: public, max-age=31536000, immutable");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . " GMT");
}

function serve_default() {
    $default_path = BASE_PATH . '/assets/images/og_default.jpg';
    if (file_exists($default_path)) {
        send_image_headers('image/jpeg', filesize($default_path), filemtime($default_path));
        readfile($default_path);
    } else {
        header("HTTP/1.1 404 Not Found");
    }
    exit;
}
