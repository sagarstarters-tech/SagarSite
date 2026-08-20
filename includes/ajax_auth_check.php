<?php
/**
 * AJAX Login State & Cart Synchronization
 * This utility detects the state regardless of SITE_URL settings.
 */
ob_start();
include_once __DIR__ . '/session_setup.php';
include_once __DIR__ . '/db_connect.php';

// Auto-detect base URLs for JS use
$proto = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$detected_site_url = defined('SITE_URL') && !empty(SITE_URL) ? rtrim(SITE_URL, '/') : "$proto://$host";
$detected_site_url = preg_replace('#/(includes|admin|api|user|auth)$#i', '', $detected_site_url);

$detected_assets_url = defined('ASSETS_URL') && !empty(ASSETS_URL) ? ASSETS_URL : $detected_site_url . '/assets';

// Sync latest user profile data from DB
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $usr_q = $conn->query("SELECT name, role, profile_photo, google_avatar FROM users WHERE id=$uid");
    if ($usr_q && $usr_q->num_rows > 0) {
        $u_row = $usr_q->fetch_assoc();
        $_SESSION['name'] = $u_row['name'];
        $_SESSION['role'] = $u_row['role'];
        $_SESSION['profile_photo'] = trim($u_row['profile_photo'] ?: ($u_row['google_avatar'] ?? ''));
    }
}

// Resolve profile photo URL using global helper
$profile_photo_url = resolve_profile_photo_url($_SESSION['profile_photo'] ?? '', $_SESSION['role'] ?? '');

$response = [
    'logged_in' => isset($_SESSION['user_id']),
    'name' => $_SESSION['name'] ?? '',
    'role' => $_SESSION['role'] ?? '',
    'profile_photo' => $_SESSION['profile_photo'] ?? '',
    'profile_photo_url' => $profile_photo_url,
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

// Clean any unexpected output/warnings from buffer before returning JSON
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode($response);
exit;

