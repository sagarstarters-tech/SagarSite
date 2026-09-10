<?php
/**
 * my-orders.php
 * Unified Order Tracking & Customer Orders entry point.
 *
 * - If user is logged in and no specific order is requested: Directs to user/orders.php
 * - If user is not logged in OR specific order tracking is requested: Loads track_order.php
 */

require_once __DIR__ . '/includes/session_setup.php';

// If user is logged in and not tracking a specific order ID, redirect to their account orders list
if (isset($_SESSION['user_id']) && empty($_GET['order_id']) && empty($_GET['track'])) {
    $targetUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/user/orders.php' : 'user/orders.php';
    header("Location: " . $targetUrl);
    exit;
}

// Render the Track Order page cleanly
require_once __DIR__ . '/track_order.php';
