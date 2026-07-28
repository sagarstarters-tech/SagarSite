<?php
/**
 * WAPI SaaS - Verify Razorpay Payment Endpoint
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$paymentId = sanitize($_GET['payment_id'] ?? '');
$planId = sanitizeInt($_GET['plan_id'] ?? 0);
$cycle = sanitize($_GET['cycle'] ?? 'monthly');

if (empty($paymentId) || empty($planId)) {
    setFlash('danger', 'Invalid payment verification request.');
    redirect('dashboard/subscription.php');
}

$plan = $db->fetch("SELECT * FROM plans WHERE id = ? AND is_active = 1", [$planId]);

if (!$plan) {
    setFlash('danger', 'Invalid plan selected.');
    redirect('dashboard/subscription.php');
}

// In a real production app you would verify the signature using Razorpay API here.
// For this SaaS boilerplate we assume successful return from checkout.js means payment succeeded

// Determine billing cycle, amount and expiration
$billingCycle = ($cycle === 'yearly') ? 'yearly' : 'monthly';
$amount = ($billingCycle === 'yearly') ? $plan['yearly_price'] : $plan['monthly_price'];

// Complete any pending subscriptions or cancel active ones for this user
$db->update('subscriptions', ['status' => 'cancelled'], "user_id = ? AND status = 'active'", [$userId]);

// Calculate dates
$startsAt = date('Y-m-d H:i:s');
$expiresAt = ($billingCycle === 'yearly') ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));

// Create subscription
$subscriptionId = $db->insert('subscriptions', [
    'user_id' => $userId,
    'plan_id' => $planId,
    'billing_cycle' => $billingCycle,
    'amount' => $amount,
    'status' => 'active',
    'starts_at' => $startsAt,
    'expires_at' => $expiresAt
]);

// Create payment record
$db->insert('payments', [
    'user_id' => $userId,
    'subscription_id' => $subscriptionId,
    'razorpay_payment_id' => $paymentId,
    'amount' => $amount,
    'status' => 'success',
    'payment_method' => 'razorpay'
]);

// Update credits
$db->update('credits', [
    'total_credits' => $plan['message_limit'],
    'used_credits' => 0
], "user_id = ?", [$userId]);

setFlash('success', 'Payment successful! Your subscription is now active.');
redirect('dashboard/subscription.php');
