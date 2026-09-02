<?php
/**
 * AJAX Mark Customers Seen
 * Location: /admin/ajax_mark_customers_seen.php
 */

require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';

AuthMiddleware::check($conn);

header('Content-Type: application/json; charset=utf-8');

$latest_id = 0;
if (isset($conn)) {
    $res = $conn->query("SELECT MAX(id) as max_id FROM users WHERE role = 'user'");
    if ($res && $row = $res->fetch_assoc()) {
        $latest_id = (int)($row['max_id'] ?? 0);
        if ($latest_id > 0) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_last_seen_customer_id', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            if ($stmt) {
                $str_id = (string)$latest_id;
                $stmt->bind_param('s', $str_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

echo json_encode(['success' => true, 'last_seen_id' => $latest_id]);
