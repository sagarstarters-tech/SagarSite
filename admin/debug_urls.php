<?php
require_once dirname(__DIR__) . '/includes/db_connect.php';
$res = $conn->query("SELECT id, file_url FROM media_library ORDER BY id DESC LIMIT 10");
echo "<pre>";
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | URL: [" . htmlspecialchars($row['file_url']) . "]\n";
}
echo "</pre>";
?>
