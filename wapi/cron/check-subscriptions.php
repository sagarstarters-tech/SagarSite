<?php
/**
 * WAPI SaaS - Subscription Expiry Checker
 * This script finds expired subscriptions, disables them, and notifies users.
 * Recommended to run via CRON every hour.
 */
require_once __DIR__ . '/../config/config.php';

$db = Database::getInstance();
$settings = new Settings();

// 1. Find all active subscriptions that have expired
$expiredSubs = $db->fetchAll("
    SELECT s.*, u.name as user_name, u.email as user_email, p.name as plan_name 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN plans p ON s.plan_id = p.id
    WHERE s.status = 'active' 
    AND s.expires_at <= NOW()
");

$processed = 0;

foreach ($expiredSubs as $sub) {
    // 2. Set subscription to expired
    $db->update('subscriptions', ['status' => 'expired'], 'id = ?', [$sub['id']]);

    // 3. Send email notification to user
    $siteName = $settings->get('site_name', 'WAPI');
    $subject = "Your Subscription has Expired - $siteName";
    
    $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #ef4444;'>Subscription Expired!</h2>
            <p>Hi <strong>{$sub['user_name']}</strong>,</p>
            <p>Your subscription to the <strong>{$sub['plan_name']}</strong> plan has expired as of {$sub['expires_at']}.</p>
            <p>To continue using our services and sending messages, please renew your plan now.</p>
            <div style='margin-top: 30px; text-align: center;'>
                <a href='" . APP_URL . "/dashboard/subscription.php' style='background: #6c63ff; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Renew Subscription</a>
            </div>
            <p style='margin-top: 30px; font-size: 0.875rem; color: #666;'>If you have already renewed, please ignore this message.</p>
            <hr style='margin: 30px 0; border: 0; border-top: 1px solid #eee;'>
            <p style='font-size: 0.75rem; color: #999; text-align: center;'>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
        </div>
    ";

    Mail::send($sub['user_email'], $subject, $body);
    $processed++;
}

echo "Subscription Expiry Check Completed. processed: $processed expired subscriptions.\n";
