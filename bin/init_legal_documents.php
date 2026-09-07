<?php
require_once __DIR__ . '/../includes/db_connect.php';

// 1. Ensure table documents exists with full schema
$conn->query("CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `doc_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Ensure columns exist
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM `documents`");
while ($r = $res->fetch_assoc()) {
    $cols[] = $r['Field'];
}

if (!in_array('doc_number', $cols)) {
    $conn->query("ALTER TABLE `documents` ADD COLUMN `doc_number` varchar(100) DEFAULT NULL AFTER `title`");
}
if (!in_array('description', $cols)) {
    $conn->query("ALTER TABLE `documents` ADD COLUMN `description` text DEFAULT NULL AFTER `doc_number`");
}

// 2. Insert default settings if not exists
$default_settings = [
    'about_docs_enabled'  => '1',
    'about_docs_title'    => 'Government Certifications & Legal Documents',
    'about_docs_subtitle' => 'Official compliance, registration certificates, and quality standards of Sagar Starters.'
];

foreach ($default_settings as $k => $v) {
    $safe_k = $conn->real_escape_string($k);
    $safe_v = $conn->real_escape_string($v);
    $check = $conn->query("SELECT setting_key FROM settings WHERE setting_key='$safe_k'");
    if (!$check || $check->num_rows === 0) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$safe_k', '$safe_v')");
    }
}

// 3. Remove old dummy documents if their image files do not exist
$dummy_check = $conn->query("SELECT id, image FROM documents WHERE image IN ('doc_1.jpg', 'doc_2.jpg', 'doc_3.jpg', 'doc_4.jpg')");
if ($dummy_check && $dummy_check->num_rows > 0) {
    while ($row = $dummy_check->fetch_assoc()) {
        $img = $row['image'];
        if (!file_exists(__DIR__ . '/../uploads/' . $img) && !file_exists(__DIR__ . '/../uploads/documents/' . $img)) {
            $conn->query("DELETE FROM documents WHERE id=" . intval($row['id']));
        }
    }
}

echo "Database initialization completed successfully.\n";
