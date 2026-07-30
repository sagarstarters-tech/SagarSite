<?php
include_once __DIR__ . '/../includes/session_setup.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized");
}
$logFile = __DIR__ . '/../logs/cart_abandonment_whatsapp.log';
echo "<h2>WhatsApp Logs</h2>";
if (file_exists($logFile)) {
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc; max-height: 80vh; overflow:auto;'>";
    echo htmlspecialchars(file_get_contents($logFile));
    echo "</pre>";
} else {
    echo "Log file not found.";
}
?>
