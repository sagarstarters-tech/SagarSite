<?php
include_once 'session_setup.php';
include_once 'db_connect.php';
require_once 'cart_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check available stock
    $stock = 0;
    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $stock = intval($res->fetch_assoc()['stock']);
        }
        $stmt->close();
    }

    if ($action === 'add') {
        $qty = intval($_POST['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;

        $current_qty = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
        $new_qty = $current_qty + $qty;

        if ($new_qty > $stock) {
            $new_qty = $stock; // Limit to max stock
        }

        if ($new_qty > 0) {
            $_SESSION['cart'][$product_id] = $new_qty;
        }
        
        sync_cart_to_db($conn);
        track_abandoned_cart($conn);
        header("Location: ../cart.php");
        exit;
    }

    if ($action === 'update') {
        $qty = intval($_POST['quantity'] ?? 1);
        if ($qty > $stock) {
            $qty = $stock;
        }
        
        if ($qty > 0) {
            $_SESSION['cart'][$product_id] = $qty;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
        sync_cart_to_db($conn);
        track_abandoned_cart($conn);
        header("Location: ../cart.php");
        exit;
    }

    if ($action === 'remove') {
        unset($_SESSION['cart'][$product_id]);
        sync_cart_to_db($conn);
        track_abandoned_cart($conn);
        header("Location: ../cart.php");
        exit;
    }
}
