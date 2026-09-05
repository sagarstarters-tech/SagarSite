<?php
$rootDir = realpath(__DIR__ . '/..');
$zipFile = __DIR__ . '/Ecommerce_Store_Package.zip';

if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("[ERROR] Could not create ZIP file at: $zipFile\n");
}

echo "Building clean E-Commerce ZIP package...\n";

$excludeList = [
    '.git',
    '.env',
    'backups',
    'logs',
    'scratch',
    'export_ready_to_sell',
    'ecommerce_db_backup.sql',
    'wapi'
];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$addedCount = 0;

foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($rootDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    // Check if any excluded directory or file matches the start of relative path
    $skip = false;
    foreach ($excludeList as $exc) {
        if ($relativePath === $exc || strpos($relativePath, $exc . '/') === 0) {
            $skip = true;
            break;
        }
    }

    if ($skip) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } elseif ($file->isFile()) {
        $zip->addFile($filePath, $relativePath);
        $addedCount++;
    }
}

// Add sanitized setup files from export_ready_to_sell
$extraFiles = [
    'clean_schema.sql' => __DIR__ . '/clean_schema.sql',
    '.env.example' => __DIR__ . '/.env.example',
    'INSTALLATION_GUIDE.md' => __DIR__ . '/INSTALLATION_GUIDE.md',
    'FEATURE_CATALOG.md' => __DIR__ . '/FEATURE_CATALOG.md'
];

foreach ($extraFiles as $archiveName => $diskPath) {
    if (file_exists($diskPath)) {
        $zip->addFile($diskPath, $archiveName);
        $addedCount++;
    }
}

$zip->close();

$sizeMB = round(filesize($zipFile) / (1024 * 1024), 2);
echo "[SUCCESS] Packaged $addedCount files successfully!\n";
echo "ZIP Location: $zipFile ($sizeMB MB)\n";
