<?php
/**
 * WAPI - Image Upload Diagnostic Test
 * Visit: /wapi/api/test-image-upload.php
 * DELETE after testing!
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Image Upload Diagnostic</title>
<style>
body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #eee; }
.ok { color: #4ade80; } .err { color: #f87171; } .warn { color: #fbbf24; }
pre { background: #0f0f1a; padding: 15px; border-radius: 8px; overflow-x: auto; }
h2 { color: #818cf8; border-bottom: 1px solid #333; padding-bottom: 8px; }
</style>
</head>
<body>
<h1>🔍 Image Upload Diagnostic</h1>

<h2>1. Configuration Check</h2>
<pre>
APP_URL   = <?= defined('APP_URL') ? '<span class="ok">'.APP_URL.'</span>' : '<span class="err">NOT DEFINED</span>' ?>
UPLOAD_DIR = <?= defined('UPLOAD_DIR') ? '<span class="ok">'.UPLOAD_DIR.'</span>' : '<span class="err">NOT DEFINED</span>' ?>
APP_ENV   = <?= defined('APP_ENV') ? APP_ENV : 'NOT DEFINED' ?>
</pre>

<h2>2. Upload Folder Check</h2>
<pre>
<?php
$uploadDir = UPLOAD_DIR . 'chatbot/';
echo "Path: $uploadDir\n";
echo "Exists: " . (is_dir($uploadDir) ? '<span class="ok">YES</span>' : '<span class="err">NO</span>') . "\n";
echo "Writable: " . (is_writable($uploadDir) ? '<span class="ok">YES</span>' : '<span class="err">NO (FIX PERMISSIONS!)</span>') . "\n";

// Count files
$files = glob($uploadDir . '*');
$count = $files ? count($files) : 0;
echo "Files in folder: $count\n";
if ($count > 0) {
    echo "Latest files:\n";
    $sorted = array_slice(array_reverse($files), 0, 5);
    foreach ($sorted as $f) {
        $name = basename($f);
        $url = rtrim(APP_URL, '/') . '/uploads/chatbot/' . $name;
        echo "  - $name → <a href='$url' target='_blank' style='color:#818cf8'>$url</a>\n";
    }
}
?>
</pre>

<h2>3. Generated URL Format</h2>
<pre>
<?php
$testUrl = rtrim(APP_URL, '/') . '/uploads/chatbot/test_image.jpg';
echo "Sample URL: <span class='ok'>$testUrl</span>\n";
echo "\nIs localhost URL? ";
if (strpos($testUrl, 'localhost') !== false || strpos($testUrl, '127.0.0.1') !== false) {
    echo "<span class='err'>YES - Meta API CANNOT access this! Update APP_URL in .env</span>\n";
} else {
    echo "<span class='ok'>NO - Public URL ✓</span>\n";
}
?>
</pre>

<h2>4. PHP Upload Settings</h2>
<pre>
file_uploads      = <?= ini_get('file_uploads') ? '<span class="ok">On</span>' : '<span class="err">Off</span>' ?>
upload_max_filesize = <?= ini_get('upload_max_filesize') ?>
post_max_size       = <?= ini_get('post_max_size') ?>
max_execution_time  = <?= ini_get('max_execution_time') ?>s
</pre>

<h2>5. Test Upload (POST a file to verify)</h2>
<form method="POST" enctype="multipart/form-data" style="background:#0f0f1a; padding:15px; border-radius:8px;">
    <input type="file" name="test_file" accept="image/*" style="color:#eee; margin-bottom:10px; display:block;">
    <button type="submit" name="do_test" value="1" style="background:#818cf8; color:#fff; border:none; padding:8px 20px; border-radius:4px; cursor:pointer;">
        Test Upload
    </button>
</form>

<?php if (isset($_POST['do_test']) && isset($_FILES['test_file'])): ?>
<h2>Upload Test Result</h2>
<pre>
<?php
$f = $_FILES['test_file'];
echo "File name: {$f['name']}\n";
echo "File size: " . number_format($f['size']/1024, 2) . " KB\n";
echo "Error code: {$f['error']}\n";

if ($f['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $newName = 'test_' . time() . '.' . $ext;
    $dest = UPLOAD_DIR . 'chatbot/' . $newName;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        $url = rtrim(APP_URL, '/') . '/uploads/chatbot/' . $newName;
        echo "\n<span class='ok'>✓ Upload SUCCESS!</span>\n";
        echo "Saved to: $dest\n";
        echo "Public URL: <a href='$url' target='_blank' style='color:#818cf8'>$url</a>\n";
        echo "\n<span class='warn'>⚠ Copy this URL and test in browser. If image opens, Meta API can use it.</span>\n";
    } else {
        echo "<span class='err'>✗ move_uploaded_file() FAILED - Check folder write permissions</span>\n";
    }
} else {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (php.ini limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'No tmp directory',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION => 'Blocked by PHP extension',
    ];
    echo "<span class='err'>✗ Upload Error: " . ($errors[$f['error']] ?? 'Unknown error ' . $f['error']) . "</span>\n";
}
?>
</pre>
<?php endif; ?>

<p style="color:#555; font-size:12px; margin-top:40px;">⚠ DELETE this file after testing: /api/test-image-upload.php</p>
</body>
</html>
