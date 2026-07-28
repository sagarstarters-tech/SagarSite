<?php
/**
 * WAPI SaaS - Submit Manual Payment UTR
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();

// --- START: Auto-Migration for Live Database ---
try {
    $planIdExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'plan_id'");
    if (!$planIdExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `plan_id` INT(11) NULL AFTER `user_id`");
    }
    
    $utrExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'utr_number'");
    if (!$utrExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `utr_number` VARCHAR(100) NULL AFTER `razorpay_signature`");
    }
    
    $cycleExists = $db->fetch("SHOW COLUMNS FROM `payments` LIKE 'billing_cycle'");
    if (!$cycleExists) {
        $db->query("ALTER TABLE `payments` ADD COLUMN `billing_cycle` VARCHAR(20) DEFAULT 'monthly' AFTER `plan_id`");
    }
} catch (Exception $e) {
    // Ignore error if it fails
}
// --- END: Auto-Migration ---

$userId = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $planId = sanitizeInt($_POST['plan_id'] ?? 0);
    $utr = sanitize($_POST['utr'] ?? '');
    $billingCycle = sanitize($_POST['billing_cycle'] ?? 'monthly');
    if ($billingCycle !== 'yearly') $billingCycle = 'monthly';

    if (!$planId || !$utr) {
        error_log("Payment Submit: Missing plan_id ($planId) or utr ($utr)");
        setFlash('danger', 'Invalid request. Please provide UTR number.');
        redirect('dashboard/subscription.php');
    }

    error_log("Payment Submit: user_id: $userId, plan_id: $planId, utr: $utr");
    
    $plan = $db->fetch("SELECT * FROM plans WHERE id = ? AND is_active = 1", [$planId]);
    if (!$plan) {
        error_log("Payment Submit: Plan not found for ID: $planId");
        setFlash('danger', 'Selected plan is not available.');
        redirect('dashboard/subscription.php');
    }

    try {
        $paymentId = $db->insert('payments', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
            'utr_number' => $utr,
            'amount' => str_replace(',', '', (string)(($billingCycle === 'yearly') ? $plan['yearly_price'] : $plan['monthly_price'])),
            'currency' => 'INR',
            'payment_method' => 'UPI',
            'status' => 'pending',
            'notes' => 'Manual UPI payment with UTR: ' . $utr . ' (' . ucfirst($billingCycle) . ')'
        ]);
        error_log("Payment Submit: Insert success, ID: $paymentId");
    } catch (\Exception $e) {
        error_log("Payment Submit ERROR: " . $e->getMessage());
        setFlash('danger', 'Database error: ' . $e->getMessage());
        redirect('dashboard/subscription.php');
    }

    if ($paymentId) {
        setFlash('success', 'Your payment proof has been submitted! Admin will verify and activate your plan soon.');
    } else {
        setFlash('danger', 'Error submitting payment proof. Please try again.');
    }

    redirect('dashboard/subscription.php');
} else {
    redirect('dashboard/subscription.php');
}
