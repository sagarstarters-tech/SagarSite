<?php
/**
 * GoogleProfileReminderService
 * 
 * Manages automated reminder emails for customers who sign in via Google
 * but leave or move away from the website without completing their profile details.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/mail_functions.php';

class GoogleProfileReminderService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->ensureTableExists();
        $this->ensureDefaultTemplate();
    }

    /**
     * Ensure the tracking table exists in DB.
     */
    public function ensureTableExists() {
        if (!$this->conn) return;
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `google_profile_reminders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `email` varchar(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_activity_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `reminder_status` enum('pending','sent','completed','cancelled') NOT NULL DEFAULT 'pending',
                `reminder_count` int(11) NOT NULL DEFAULT 0,
                `last_sent_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status_activity` (`reminder_status`, `last_activity_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->conn->query($sql);
        } catch (\Throwable $e) {
            error_log('[GoogleProfileReminder] ensureTableExists error: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the default email template exists in email_templates table.
     */
    public function ensureDefaultTemplate() {
        if (!$this->conn) return;
        try {
            // Check if email_templates table exists
            $tblCheck = $this->conn->query("SHOW TABLES LIKE 'email_templates'");
            if (!$tblCheck || $tblCheck->num_rows === 0) {
                $this->conn->query("CREATE TABLE IF NOT EXISTS `email_templates` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tpl_key` varchar(50) NOT NULL,
                    `label` varchar(100) NOT NULL,
                    `subject` varchar(255) NOT NULL,
                    `body` text NOT NULL,
                    `placeholders` varchar(255) DEFAULT NULL,
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tpl_key` (`tpl_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }

            $chk = $this->conn->query("SELECT id FROM email_templates WHERE tpl_key = 'google_profile_reminder' LIMIT 1");
            if ($chk && $chk->num_rows === 0) {
                $tpl_key = 'google_profile_reminder';
                $label = 'Google Profile Completion Reminder';
                $subject = "Complete Your Profile at {site_name} – Quick 1-Minute Setup";
                $body = '
<div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    <!-- Header Banner -->
    <div style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); padding: 32px 24px; text-align: center; color: #ffffff;">
        <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; padding: 12px; margin-bottom: 12px;">
            <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" width="48" height="48" alt="Profile Icon" style="vertical-align: middle;">
        </div>
        <h2 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">Welcome to {site_name}!</h2>
        <p style="margin: 6px 0 0; font-size: 15px; color: rgba(255,255,255,0.9);">You are just one step away from seamless shopping & fast delivery.</p>
    </div>

    <!-- Content Body -->
    <div style="padding: 32px 28px; color: #334155; line-height: 1.6;">
        <p style="font-size: 16px; margin-top: 0;">Hi <strong>{name}</strong>,</p>
        
        <p style="font-size: 15px; margin-bottom: 20px;">
            Thank you for signing in with Google! We noticed you moved away before finishing your shipping and contact details (Phone, Delivery Address, etc.).
        </p>

        <!-- Information Callout Box -->
        <div style="background-color: #f8fafc; border-left: 4px solid #0d6efd; padding: 16px 20px; border-radius: 6px; margin: 24px 0;">
            <p style="margin: 0 0 8px; font-weight: 600; color: #1e293b; font-size: 15px;">Why complete your profile?</p>
            <ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 14px;">
                <li style="margin-bottom: 6px;">⚡ <strong>Fast Checkout:</strong> Auto-fill your delivery details instantly.</li>
                <li style="margin-bottom: 6px;">📦 <strong>Live Order Tracking:</strong> Receive WhatsApp & SMS shipment updates.</li>
                <li>🎁 <strong>Exclusive Offers:</strong> Access special member discounts.</li>
            </ul>
        </div>

        <!-- Action Button -->
        <div style="text-align: center; margin: 32px 0 24px;">
            <a href="{profile_link}" style="display: inline-block; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 14px 36px; border-radius: 50px; box-shadow: 0 4px 12px rgba(13,110,253,0.35);">
                Complete My Profile Now &rarr;
            </a>
        </div>

        <p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 20px;">
            Or copy and paste this link in your browser:<br>
            <a href="{profile_link}" style="color: #0d6efd; word-break: break-all;">{profile_link}</a>
        </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #f1f5f9; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
        <p style="margin: 0 0 6px;">This reminder was sent to <strong>{email}</strong> because you signed in to {site_name}.</p>
        <p style="margin: 0;">&copy; {current_year} {site_name}. All rights reserved.</p>
    </div>
</div>';
                $placeholders = '{name}, {email}, {profile_link}, {site_name}, {site_url}, {current_year}';

                $stmt = $this->conn->prepare("INSERT INTO email_templates (tpl_key, label, subject, body, placeholders) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $tpl_key, $label, $subject, $body, $placeholders);
                $stmt->execute();
                $stmt->close();
            }
        } catch (\Throwable $e) {
            error_log('[GoogleProfileReminder] ensureDefaultTemplate error: ' . $e->getMessage());
        }
    }

    /**
     * Get system setting value with fallback.
     */
    public function getSetting($key, $default = '') {
        if (!$this->conn) return $default;
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $stmt->close();
                return $row['setting_value'];
            }
            $stmt->close();
        } catch (\Throwable $e) {}
        return $default;
    }

    /**
     * Track a Google Login event for profile completion reminder.
     * 
     * @param int $userId
     * @param string $email
     * @param string $name
     * @return bool
     */
    public function trackGoogleLogin($userId, $email, $name) {
        $userId = intval($userId);
        if ($userId <= 0 || empty($email)) return false;

        try {
            // Check if user already has a complete profile in users table
            $uStmt = $this->conn->prepare("SELECT phone, address FROM users WHERE id = ? LIMIT 1");
            $uStmt->bind_param("i", $userId);
            $uStmt->execute();
            $uRes = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            if ($uRes && !empty($uRes['phone']) && !empty($uRes['address'])) {
                // Profile is already complete, mark any existing record completed and don't schedule reminder
                $this->markCompleted($userId);
                return true;
            }

            // Check if there is an existing pending record
            $chk = $this->conn->prepare("SELECT id, reminder_status FROM google_profile_reminders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $chk->bind_param("i", $userId);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($row) {
                // Reset to pending with reminder_count = 0 so this session is eligible for reminder
                $upd = $this->conn->prepare("UPDATE google_profile_reminders SET reminder_status = 'pending', reminder_count = 0, email = ?, name = ?, login_at = NOW(), last_activity_at = NOW() WHERE id = ?");
                $upd->bind_param("ssi", $email, $name, $row['id']);
                $res = $upd->execute();
                $upd->close();
                return $res;
            }

            // Insert new pending record
            $ins = $this->conn->prepare("INSERT INTO google_profile_reminders (user_id, email, name, login_at, last_activity_at, reminder_status, reminder_count) VALUES (?, ?, ?, NOW(), NOW(), 'pending', 0)");
            $ins->bind_param("iss", $userId, $email, $name);
            $res = $ins->execute();
            $ins->close();
            return $res;
        } catch (\Throwable $e) {
            error_log('[GoogleProfileReminder] trackGoogleLogin error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user's last activity timestamp (customer is still browsing).
     * 
     * @param int $userId
     */
    public function touchActivity($userId) {
        $userId = intval($userId);
        if ($userId <= 0) return;

        try {
            $stmt = $this->conn->prepare("UPDATE google_profile_reminders SET last_activity_at = NOW() WHERE user_id = ? AND reminder_status = 'pending'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {}
    }

    /**
     * Mark profile as completed for a user (stops/cancels any reminders).
     * 
     * @param int $userId
     */
    public function markCompleted($userId) {
        $userId = intval($userId);
        if ($userId <= 0) return;

        try {
            $stmt = $this->conn->prepare("UPDATE google_profile_reminders SET reminder_status = 'completed' WHERE user_id = ? AND reminder_status IN ('pending', 'sent')");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log('[GoogleProfileReminder] markCompleted error: ' . $e->getMessage());
        }
    }

    /**
     * Process due automated reminders.
     * Called by background shutdown function or cron.
     * 
     * @return int Number of reminders sent
     */
    public function processAutoReminders() {
        if (!$this->conn) return 0;

        // Check if reminders are enabled globally in settings
        $enabled = $this->getSetting('google_profile_reminder_enabled', '1');
        if ($enabled !== '1') {
            return 0;
        }

        // Delay in minutes after moving away / inactivity (default 15 minutes)
        $delayMinutes = intval($this->getSetting('google_profile_reminder_delay', '15'));
        if ($delayMinutes < 0) $delayMinutes = 0;

        // Max reminders per user (default 1)
        $maxCount = intval($this->getSetting('google_profile_reminder_max_count', '1'));
        if ($maxCount < 1) $maxCount = 1;

        $sentCount = 0;

        try {
            // Find pending reminders where inactivity duration is >= delayMinutes
            if ($delayMinutes === 0) {
                $query = "SELECT r.*, u.phone, u.address, u.is_verified, u.name as current_name, u.email as current_email 
                          FROM google_profile_reminders r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.reminder_status = 'pending' 
                            AND r.reminder_count < ? 
                          ORDER BY r.id ASC 
                          LIMIT 20";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $maxCount);
            } else {
                $query = "SELECT r.*, u.phone, u.address, u.is_verified, u.name as current_name, u.email as current_email 
                          FROM google_profile_reminders r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.reminder_status = 'pending' 
                            AND r.reminder_count < ? 
                            AND TIMESTAMPDIFF(MINUTE, r.last_activity_at, NOW()) >= ? 
                          ORDER BY r.id ASC 
                          LIMIT 20";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ii", $maxCount, $delayMinutes);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();

            foreach ($records as $rec) {
                // If the user already completed profile in users table, mark completed and skip
                if (!empty($rec['phone']) && !empty($rec['address'])) {
                    $this->markCompleted($rec['user_id']);
                    continue;
                }

                $recipientEmail = !empty($rec['current_email']) ? $rec['current_email'] : $rec['email'];
                $recipientName = !empty($rec['current_name']) ? $rec['current_name'] : $rec['name'];

                $sent = $this->sendReminderEmail($rec['id'], $rec['user_id'], $recipientEmail, $recipientName);
                if ($sent) {
                    $sentCount++;
                }
            }
        } catch (\Throwable $e) {
            error_log('[GoogleProfileReminder] processAutoReminders exception: ' . $e->getMessage());
        }

        return $sentCount;
    }

    /**
     * Send profile completion reminder email.
     * 
     * @param int $reminderId
     * @param int $userId
     * @param string $recipientEmail
     * @param string $recipientName
     * @return bool
     */
    public function sendReminderEmail($reminderId, $userId, $recipientEmail, $recipientName) {
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            logEmailAttempt($this->conn, 0, $recipientEmail, 'google_profile_reminder', 'failed', 'Invalid email address.');
            return false;
        }

        // Global email notifications check
        $globalEmailEnabled = $this->getSetting('enable_email_notifications', '1');
        if ($globalEmailEnabled !== '1') {
            return false;
        }

        try {
            $siteName = $this->getSetting('site_name', "Sagar Starter's");
            $baseUrl = defined('SITE_URL') && !empty(SITE_URL) ? rtrim(SITE_URL, '/') : '';
            if (empty($baseUrl)) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = $proto . '://' . $host;
            }
            $profileLink = $baseUrl . '/user/profile.php';

            // Fetch template
            $tpl = getEmailTemplate($this->conn, 'google_profile_reminder');

            $vars = [
                'name'         => htmlspecialchars($recipientName ?: 'Valued Customer'),
                'email'        => htmlspecialchars($recipientEmail),
                'profile_link' => $profileLink,
                'site_name'    => htmlspecialchars($siteName),
                'site_url'     => $baseUrl,
                'current_year' => date('Y')
            ];

            if ($tpl) {
                $subject = parseTemplate($tpl['subject'], $vars);
                $body = parseTemplate($tpl['body'], $vars);
            } else {
                $subject = "Complete Your Profile at " . $siteName;
                $body = "<p>Hi {$vars['name']},</p><p>Please complete your profile details: <a href='{$profileLink}'>{$profileLink}</a></p>";
            }

            $mail = getMailerInstance($this->conn);
            $mail->addAddress($recipientEmail, $recipientName ?: '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            // Log attempt
            logEmailAttempt($this->conn, 0, $recipientEmail, 'google_profile_reminder', 'success');

            // Update reminder record
            $upd = $this->conn->prepare("UPDATE google_profile_reminders SET reminder_status = 'sent', reminder_count = reminder_count + 1, last_sent_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $reminderId);
            $upd->execute();
            $upd->close();

            return true;
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            logEmailAttempt($this->conn, 0, $recipientEmail, 'google_profile_reminder', 'failed', "Error: " . $errMsg);
            error_log("[GoogleProfileReminder] sendReminderEmail failed for user #{$userId}: " . $errMsg);
            return false;
        }
    }
}
