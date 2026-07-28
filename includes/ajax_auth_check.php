<?php
/**
 * AJAX Login State & Cart Synchronization
 * This utility detects the state regardless of SITE_URL settings.
 */
include_once __DIR__ . '/session_setup.php';
include_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

// Auto-detect base URLs for JS use
$proto = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$detected_site_url = defined('SITE_URL') && !empty(SITE_URL) ? rtrim(SITE_URL, '/') : "$proto://$host";
$detected_site_url = preg_replace('#/(includes|admin|api|user|auth)$#i', '', $detected_site_url);

$detected_assets_url = defined('ASSETS_URL') && !empty(ASSETS_URL) ? rtrim(ASSETS_URL, '/') : $detected_site_url . '/assets';

$response = [
    'logged_in' => isset($_SESSION['user_id']),
    'name' => $_SESSION['name'] ?? '',
    'role' => $_SESSION['role'] ?? '',
    'profile_photo' => $_SESSION['profile_photo'] ?? '',
    'cart_count' => 0,
    'cart_total' => 0,
    'global_currency' => $global_currency ?? '₹',
    'site_url' => $detected_site_url,
    'assets_url' => $detected_assets_url,
    'needs_profile_update' => isset($_SESSION['needs_profile_update']) ? $_SESSION['needs_profile_update'] : false
];

// Calculate cart logic
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    if (count($product_ids) > 0) {
        $ids_str = implode(',', array_map('intval', $product_ids));
        $price_q = $conn->query("SELECT id, price FROM products WHERE id IN ($ids_str)");
        $prices = [];
        if ($price_q) {
            while ($row = $price_q->fetch_assoc()) {
                $prices[$row['id']] = $row['price'];
            }
        }
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (isset($prices[$pid])) {
                $response['cart_count'] += $qty;
                $response['cart_total'] += ($prices[$pid] * $qty);
            }
        }
    }
}

echo json_encode($response);
exit;
