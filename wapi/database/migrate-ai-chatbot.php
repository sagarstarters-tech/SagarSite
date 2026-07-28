<?php
/**
 * AI ChatBot Builder - One-time Database Migration Script
 * Run this file once via browser, then DELETE it for security.
 * URL: https://wapi.sagarstarters.com/database/migrate-ai-chatbot.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Security: Only allow logged-in admin users
if (!Auth::isLoggedIn()) {
    die('Unauthorized. Please login first.');
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$results = [];

// Read the SQL file
$sqlFile = __DIR__ . '/ai_chatbot_schema.sql';
if (!file_exists($sqlFile)) {
    die('SQL file not found: ' . $sqlFile);
}

$sql = file_get_contents($sqlFile);

// Split by statement (handle multi-line statements)
// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Split by semicolons (but not inside quotes)
$statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));

$success = 0;
$failed = 0;
$skipped = 0;

echo "<!DOCTYPE html><html><head><title>AI ChatBot Migration</title>
<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;}
.ok{color:#4ecca3;}.err{color:#ff6b6b;}.skip{color:#ffd93d;}.info{color:#6bc5f7;}
h1{color:#fff;}pre{background:#16213e;padding:15px;border-radius:8px;overflow-x:auto;}
</style></head><body>";
echo "<h1>🤖 AI ChatBot Builder - Database Migration</h1><pre>";

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    
    // Show first 100 chars of statement
    $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 120);
    
    try {
        $pdo->exec($stmt);
        $success++;
        echo "<span class='ok'>✅ OK:</span> {$preview}\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // "already exists" or "Duplicate column" are expected on re-run
        if (strpos($msg, 'already exists') !== false || strpos($msg, 'Duplicate column') !== false || strpos($msg, 'Duplicate entry') !== false) {
            $skipped++;
            echo "<span class='skip'>⏭️ SKIP:</span> {$preview}\n";
            echo "<span class='skip'>   → {$msg}</span>\n";
        } else {
            $failed++;
            echo "<span class='err'>❌ FAIL:</span> {$preview}\n";
            echo "<span class='err'>   → {$msg}</span>\n";
        }
    }
}

echo "\n<span class='info'>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span>\n";
echo "<span class='ok'>✅ Success: {$success}</span>\n";
echo "<span class='skip'>⏭️ Skipped: {$skipped}</span>\n";
echo "<span class='err'>❌ Failed:  {$failed}</span>\n";
echo "\n<span class='info'>⚠️  DELETE this file after migration!</span>";
echo "</pre></body></html>";
