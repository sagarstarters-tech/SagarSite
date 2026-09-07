<?php
/**
 * ============================================================
 *  COD Charges API Endpoint
 *  Location: /api/cod_charges.php
 * ============================================================
 *  Returns real-time COD charge calculations for the current cart.
 *  Called via AJAX from checkout when payment method changes.
 *
 *  Method: POST (with CSRF token) or GET (read-only, session-based)
 *  Response: JSON
 * ============================================================
 */

header('Content-Type: application/json');

// Bootstrap
include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/CodService.php';

// Auth check — must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Must have cart items
if (empty($_SESSION['cart'])) {
    echo json_encode([
        'success'    => true,
        'cod_charge' => 0,
        'is_free'    => false,
        'message'    => 'Cart is empty',
        'is_blacklisted' => false,
    ]);
    exit;
}

// Fetch cart products
$safe_ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
$result = !empty($safe_ids) ? $conn->query("SELECT * FROM products WHERE id IN ($safe_ids)") : false;

$cart_items = [];
$subtotal = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $qty = (int) $_SESSION['cart'][$row['id']];
        if ($qty > $row['stock']) {
            $qty = $row['stock'];
        }
        if ($qty > 0) {
            $row['qty'] = $qty;
            $subtotal += (float) $row['price'] * $qty;
            $cart_items[] = $row;
        }
    }
}

// Instantiate COD service
$codService = new CodService($conn, $global_settings);

// Calculate charge
$charge_result = $codService->calculateCodCharge($cart_items, $subtotal);

// Check blacklist
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$blacklist_result = $codService->isBlacklisted(
    $user['phone'] ?? '',
    $user['email'] ?? '',
    $_SERVER['REMOTE_ADDR'] ?? ''
);

// Build response
echo json_encode([
    'success'          => true,
    'cod_charge'       => $charge_result['cod_charge'],
    'is_free'          => $charge_result['is_free'],
    'free_threshold'   => $charge_result['free_threshold'],
    'charge_mode'      => $charge_result['charge_mode'],
    'message'          => $charge_result['message'],
    'is_blacklisted'   => $blacklist_result['is_blacklisted'],
    'blacklist_reason' => $blacklist_result['is_blacklisted'] ? 'COD is not available for your account.' : null,
    'subtotal'         => $subtotal,
    'currency'         => $global_currency,
]);
