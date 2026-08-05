<?php
/**
 * Clear All Media & Reset Image References Script
 * Wipes media_library, clears image columns across all tables, and deletes uploaded files.
 */

header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';

// Auth check (allow CLI or authenticated admin session)
if (php_sapi_name() !== 'cli') {
    try {
        AuthMiddleware::check($conn);
    } catch (Exception $e) {
        http_response_code(403);
        echo "Unauthorized access.";
        exit;
    }
}

echo "=== STARTING COMPLETE MEDIA CLEANUP ===" . PHP_EOL . PHP_EOL;

// 1. Truncate media_library
$conn->query("TRUNCATE TABLE `media_library`");
echo "✓ Truncated media_library" . PHP_EOL;

// 2. Truncate product_images
$conn->query("TRUNCATE TABLE `product_images`");
echo "✓ Truncated product_images" . PHP_EOL;

// 3. Reset image columns in existing tables
$reset_tables = ['products', 'banners', 'categories', 'hero_slides', 'sliders', 'testimonials', 'homepage_features', 'slides'];

foreach ($reset_tables as $tbl) {
    $texists = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($texists && $texists->num_rows > 0) {
        $cexists = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE 'image'");
        if ($cexists && $cexists->num_rows > 0) {
            $conn->query("UPDATE `$tbl` SET `image` = '' WHERE `image` IS NOT NULL");
            echo "✓ Cleared 'image' column in table: $tbl" . PHP_EOL;
        }
    }
}

// 4. Delete physical files from uploads directories
$upload_dirs = [
    realpath(__DIR__ . '/../uploads/media/images'),
    realpath(__DIR__ . '/../uploads/media/videos'),
    realpath(__DIR__ . '/../uploads/images'),
];

$deleted_files_count = 0;

function wipe_directory($dir, &$count) {
    if (!$dir || !is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === '.gitkeep' || $item === '.gitignore') {
            continue;
        }
        $full_path = $dir . '/' . $item;
        if (is_dir($full_path)) {
            wipe_directory($full_path, $count);
            @rmdir($full_path);
        } else {
            if (@unlink($full_path)) {
                $count++;
            }
        }
    }
}

foreach ($upload_dirs as $udir) {
    if ($udir) {
        wipe_directory($udir, $deleted_files_count);
    }
}

echo "✓ Deleted $deleted_files_count physical media file(s) from uploads folder." . PHP_EOL;

echo PHP_EOL . "=== CLEANUP COMPLETED SUCCESSFULLY! ===" . PHP_EOL;
