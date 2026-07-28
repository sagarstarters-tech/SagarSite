<?php
/**
 * WAPI SaaS - Contact Form API Handler
 * Receives contact form submissions and stores them in the database
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Rate limiting: max 5 submissions per 10 minutes
if (!rateLimit('contact_form_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later.']);
    exit;
}

// Collect & sanitize input
$firstName   = sanitize($_POST['first_name'] ?? '');
$lastName    = sanitize($_POST['last_name'] ?? '');
$email       = sanitizeEmail($_POST['email'] ?? '');
$countryCode = sanitize($_POST['country_code'] ?? '+91');
$phone       = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
$subject     = sanitize($_POST['subject'] ?? '');
$message     = sanitize($_POST['message'] ?? '');

// Build full phone with country code
$fullPhone = $countryCode . $phone;

// Validate required fields
$errors = [];
if (empty($firstName)) $errors[] = 'First name is required.';
if (empty($lastName))  $errors[] = 'Last name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if (empty($phone) || strlen($phone) < 6 || strlen($phone) > 15) $errors[] = 'A valid mobile number is required (6-15 digits).';
if (empty($subject))   $errors[] = 'Subject is required.';
if (empty($message))   $errors[] = 'Message is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $db = Database::getInstance();

    // Auto-create contact_messages table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('unread','read','replied') DEFAULT 'unread',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_status` (`status`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add phone column if it doesn't exist (for existing tables)
    try {
        $db->query("ALTER TABLE `contact_messages` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `email`");
    } catch (Exception $e) {
        // Column already exists — ignore
    }

    // Insert message
    $db->insert('contact_messages', [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'phone'      => $fullPhone,
        'subject'    => $subject,
        'message'    => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // --- Send email notification to admin ---
    $settings  = new Settings();
    $adminEmail = $settings->get('contact_email', '');
    // Fallback: if no contact_email, try SMTP_USER
    if (empty($adminEmail) && defined('SMTP_USER') && SMTP_USER) {
        $adminEmail = SMTP_USER;
    }

    if (!empty($adminEmail)) {
        $siteName   = $settings->get('site_name', 'WAPI');
        $senderName  = htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8');
        $senderEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $senderPhone = htmlspecialchars($fullPhone, ENT_QUOTES, 'UTF-8');
        $subjectSafe = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $messageSafe = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $adminUrl    = APP_URL . '/admin/contact-messages.php';
        $dateTime    = date('d M Y, h:i A');

        $emailSubject = "[{$siteName}] New Contact Message: {$subject}";
        $emailBody = "
        <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='background: linear-gradient(135deg, #6c63ff, #3b82f6); padding: 24px 30px;'>
                <h2 style='margin: 0; color: #ffffff; font-size: 20px;'>📩 New Contact Form Message</h2>
                <p style='margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;'>{$siteName} &middot; {$dateTime}</p>
            </div>
            <div style='padding: 28px 30px;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                    <tr>
                        <td style='padding: 10px 0; color: #666; width: 110px; vertical-align: top;'><strong>From:</strong></td>
                        <td style='padding: 10px 0; color: #222;'>{$senderName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #666; vertical-align: top;'><strong>Email:</strong></td>
                        <td style='padding: 10px 0;'><a href='mailto:{$senderEmail}' style='color: #6c63ff; text-decoration: none;'>{$senderEmail}</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #666; vertical-align: top;'><strong>Phone:</strong></td>
                        <td style='padding: 10px 0;'><a href='tel:{$senderPhone}' style='color: #6c63ff; text-decoration: none;'>{$senderPhone}</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #666; vertical-align: top;'><strong>Subject:</strong></td>
                        <td style='padding: 10px 0; color: #222;'>{$subjectSafe}</td>
                    </tr>
                </table>
                <div style='margin-top: 18px; padding: 18px; background: #f8f9fa; border-left: 4px solid #6c63ff; border-radius: 4px; font-size: 14px; color: #333; line-height: 1.7;'>
                    {$messageSafe}
                </div>
                <div style='margin-top: 28px; text-align: center;'>
                    <a href='{$adminUrl}' style='display: inline-block; background: #6c63ff; color: #fff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;'>View in Admin Panel</a>
                </div>
                <div style='margin-top: 20px; text-align: center;'>
                    <a href='mailto:{$senderEmail}?subject=Re:%20{$subjectSafe}' style='color: #6c63ff; text-decoration: none; font-size: 13px;'>↩ Reply directly to {$senderName}</a>
                </div>
            </div>
            <div style='background: #f8f9fa; padding: 16px 30px; text-align: center; font-size: 12px; color: #999;'>
                &copy; " . date('Y') . " {$siteName}. This is an automated notification.
            </div>
        </div>";

        // Send — errors are logged but don't break the user-facing response
        $mailResult = Mail::send($adminEmail, $emailSubject, $emailBody);
        if (!$mailResult['success']) {
            error_log("Contact form admin notification failed: " . $mailResult['message']);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully! We will get back to you soon.']);

} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
