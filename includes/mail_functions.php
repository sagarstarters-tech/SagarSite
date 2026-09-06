<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/mail_config.php';

/**
 * Log email sending attempts to the database
 */
function logEmailAttempt($conn, $order_id, $recipient, $type, $status, $error_msg = null) {
    if (!$conn) return;
    try {
        if ($error_msg) {
            $error_msg = $conn->real_escape_string($error_msg);
        }
        
        $order_id_val = (!empty($order_id) && intval($order_id) > 0) ? intval($order_id) : null;
        
        if ($order_id_val === null) {
            $stmt = $conn->prepare("INSERT INTO email_logs (order_id, recipient_email, email_type, status, error_message) VALUES (NULL, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $recipient, $type, $status, $error_msg);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO email_logs (order_id, recipient_email, email_type, status, error_message) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $order_id_val, $recipient, $type, $status, $error_msg);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (\Throwable $e) {
        error_log('[logEmailAttempt] Error: ' . $e->getMessage());
    }
}

/**
 * Fetch an email template from the database based on its key.
 * 
 * @param mysqli $conn Database connection object
 * @param string $key Unique template key
 * @return array|null Template data or null if not found
 */
function getEmailTemplate($conn, $key) {
    if (!$conn) return null;
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE tpl_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Replace placeholders in an email template.
 * 
 * @param string $content HTML/Text content with {placeholders}
 * @param array $vars Key-value pairs for replacement
 * @return string Parsed content
 */
function parseTemplate($content, $vars) {
    foreach ($vars as $key => $value) {
        $content = str_replace('{' . $key . '}', $value, $content);
    }
    return $content;
}

/**
 * Configure standard PHPMailer instance
 */
function getMailerInstance($conn = null) {
    global $conn; // Use global conn if not provided
    $db_conn = $conn;
    
    $mail = new PHPMailer(true);
    
    // Default fallback to .env constants
    $host = defined('SMTP_HOST') ? SMTP_HOST : '';
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Store System';
    $from_email = $user; // Default fallback to SMTP_USER for from-email
    
    // Check DB Settings if connection is available
    if ($db_conn) {
        $settings_keys = [
            "'smtp_provider'", "'smtp_host'", "'smtp_port'", 
            "'smtp_username'", "'smtp_password'", "'smtp_encryption'", 
            "'smtp_sender_email'", "'smtp_sender_name'"
        ];
        $keys_str = implode(',', $settings_keys);
        $res = $db_conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($keys_str)");
        
        $db_settings = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $db_settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        $provider = $db_settings['smtp_provider'] ?? 'env';
        
        if ($provider !== 'env' && !empty($db_settings['smtp_host'])) {
            $host = $db_settings['smtp_host'];
            $port = (int)$db_settings['smtp_port'];
            $user = $db_settings['smtp_username'];
            
            // Decrypt password
            $enc_pass = $db_settings['smtp_password'] ?? '';
            $pass = '';
            if (!empty($enc_pass)) {
                $encryption_key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_fallback_secret_key_123!';
                // The password is saved as base64_encode(encrypted_data::iv)
                $decoded = base64_decode($enc_pass, true);
                if ($decoded !== false && strpos($decoded, '::') !== false) {
                    list($encrypted_data, $iv) = explode('::', $decoded, 2);
                    $pass = openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
                } else {
                    $pass = $enc_pass; // Fallback if plain text happens to exist
                }
            }
            
            $secure = strtolower($db_settings['smtp_encryption'] ?? 'tls');
            $from_email = !empty($db_settings['smtp_sender_email']) ? $db_settings['smtp_sender_email'] : $user;
            $from_name = !empty($db_settings['smtp_sender_name']) ? $db_settings['smtp_sender_name'] : $from_name;
        }
    }
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = $secure;
    $mail->Port       = $port;
    
    // Explicitly set SMTP properties for better reliability on Hostinger/restricted hosts
    if ($port === 465 || $secure === 'ssl') {
        $mail->SMTPAutoTLS = false; // Prevents conflicting STARTTLS attempts on SSL port
    }
    
    // Bypass SSL certificate verification for local environments (like XAMPP)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Sender configuration
    $mail->setFrom($from_email, $from_name);
    
    return $mail;
}

/**
 * Send Order Confirmation Emails (Customer and Admin)
 */
function sendOrderConfirmationEmail($conn, $order_id, $customer_email, $customer_name, $order_details, $subtotal, $currency, $payment_method = 'card') {
    
    // Determine accurate human-readable payment method name
    $raw_method = strtolower(trim((string)$payment_method));
    
    // Check orders table if order_id is provided to detect authoritative payment info
    if ($conn && !empty($order_id)) {
        $ord_q = $conn->query("SELECT payment_method, payment_mode FROM orders WHERE id = " . intval($order_id) . " LIMIT 1");
        if ($ord_q && $ord_row = $ord_q->fetch_assoc()) {
            $db_m = strtolower(trim((string)($ord_row['payment_method'] ?? '')));
            $db_mode = strtolower(trim((string)($ord_row['payment_mode'] ?? '')));
            if (!empty($db_m) && ($raw_method === 'card' || empty($raw_method))) {
                $raw_method = $db_m;
            }
            if ($db_mode === 'partial_cod' || $db_m === 'partial_cod') {
                $raw_method = 'partial_cod';
            } elseif ($db_mode === 'phonepe' || $db_m === 'phonepe') {
                $raw_method = 'phonepe';
            }
        }
    }

    if ($raw_method === 'phonepe' || $raw_method === 'phonepe_upi' || $raw_method === 'upi') {
        $payment_text = 'PhonePe UPI';
    } elseif ($raw_method === 'partial_cod') {
        $payment_text = 'Partial COD (Advance via PhonePe UPI + Cash On Delivery)';
    } elseif ($raw_method === 'cod') {
        $payment_text = 'Cash On Delivery (COD)';
    } elseif ($raw_method === 'card' || $raw_method === 'credit_card' || $raw_method === 'debit_card') {
        $payment_text = 'Credit / Debit Card';
    } elseif ($raw_method === 'netbanking') {
        $payment_text = 'Net Banking';
    } elseif ($raw_method === 'razorpay') {
        $payment_text = 'Razorpay Online';
    } elseif (!empty($raw_method)) {
        $payment_text = ucwords(str_replace(['_', '-'], ' ', $raw_method));
    } else {
        $payment_text = 'Online Payment';
    }
    
    // 1. Check if emails are enabled globally
    $settings_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_email_notifications'");
    $emails_enabled = ($settings_q && $row = $settings_q->fetch_assoc()) ? ($row['setting_value'] ?? '0') : '0';
    
    if ($emails_enabled !== '1') {
        return false; // Emails are disabled
    }
    
    $settings_q2 = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_email'");
    $admin_email = $settings_q2->fetch_assoc()['setting_value'] ?? SMTP_USER;

    // Prevent duplicate order confirmation emails for the same order
    if ($conn && !empty($order_id)) {
        $chk_email = $conn->query("SELECT id FROM email_logs WHERE order_id = " . intval($order_id) . " AND email_type = 'customer_order' AND status = 'sent' LIMIT 1");
        if ($chk_email && $chk_email->num_rows > 0) {
            error_log("[Email] Order confirmation email already sent for Order #$order_id. Skipping duplicate.");
            return true;
        }
    }
    
    // Validate recipient emails
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        logEmailAttempt($conn, $order_id, $customer_email, 'customer_order', 'failed', 'Invalid customer email address format.');
        $customer_email = false;
    }
    
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        logEmailAttempt($conn, $order_id, $admin_email, 'admin_order', 'failed', 'Invalid admin email address format.');
        $admin_email = false;
    }

    $date_str = date('F j, Y, g:i a');
    
    // Common HTML Parts - Professional Responsive Items Table (Mobile-Optimized)
    $items_html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout: fixed; width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-top: 8px; box-sizing: border-box;">
                    <colgroup>
                        <col style="width: 48%;">
                        <col style="width: 14%;">
                        <col style="width: 19%;">
                        <col style="width: 19%;">
                    </colgroup>
                    <thead>
                        <tr style="background-color: #f8fafc;">
                            <th style="padding: 10px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Product</th>
                            <th style="padding: 10px 4px; border-bottom: 1px solid #e2e8f0; text-align: center; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Qty</th>
                            <th style="padding: 10px 4px; border-bottom: 1px solid #e2e8f0; text-align: right; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Price</th>
                            <th style="padding: 10px 6px; border-bottom: 1px solid #e2e8f0; text-align: right; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
    foreach ($order_details as $item) {
        $item_total = $item['price'] * $item['qty'];
        $items_html .= '<tr>
                            <td style="padding: 10px 6px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: middle; word-break: break-word; overflow-wrap: break-word;">
                                <div style="font-weight: 600; font-size: 12px; color: #1e293b; line-height: 1.35;">' . htmlspecialchars($item['name']) . '</div>
                            </td>
                            <td style="padding: 10px 4px; border-bottom: 1px solid #f1f5f9; text-align: center; vertical-align: middle;">
                                <span style="display: inline-block; background-color: #f1f5f9; color: #334155; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">' . $item['qty'] . '</span>
                            </td>
                            <td style="padding: 10px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; vertical-align: middle; font-size: 12px; color: #64748b; white-space: nowrap; font-variant-numeric: tabular-nums;">' . $currency . number_format($item['price'], 2) . '</td>
                            <td style="padding: 10px 6px; border-bottom: 1px solid #f1f5f9; text-align: right; vertical-align: middle; font-weight: 700; font-size: 12px; color: #0f172a; white-space: nowrap; font-variant-numeric: tabular-nums;">' . $currency . number_format($item_total, 2) . '</td>
                        </tr>';
    }
    
    $items_html .= '</tbody>
                    <tfoot>
                        <tr style="background-color: #f8fafc;">
                            <td colspan="2" style="padding: 11px 8px; text-align: right; font-weight: 700; font-size: 12px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.4px; border-top: 2px solid #e2e8f0; white-space: nowrap;">Grand Total:</td>
                            <td colspan="2" style="padding: 11px 8px; text-align: right; font-weight: 800; font-size: 15px; color: #0284c7; border-top: 2px solid #e2e8f0; white-space: nowrap;">' . $currency . number_format($subtotal, 2) . '</td>
                        </tr>
                    </tfoot>
                   </table>';
                   
    // Resolve base site url and admin order url
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $site_url = defined('SITE_URL') && !empty(SITE_URL) ? rtrim(SITE_URL, '/') : ($proto . $host . '/SagarSite');
    if (!preg_match('~^https?://~i', $site_url)) {
        $site_url = $proto . $host . '/' . ltrim($site_url, '/');
    }
    $admin_order_url = $site_url . '/admin/manage_orders.php';

    // Canonical executive layout for Customer Order Confirmation (Mobile-Optimized)
    $exec_customer_body = '<div style="background-color: #f1f5f9; padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; box-sizing: border-box; -webkit-text-size-adjust: 100%;">
    <!-- mobile-responsive-v2 -->
    <div style="max-width: 600px; width: 100%; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.05); line-height: 1.5; box-sizing: border-box;">
        <!-- Top Brand Bar -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 16px 14px; text-align: left; border-bottom: 1px solid #334155;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td style="vertical-align: middle; text-align: left;">
                        <div style="font-size: 16px; font-weight: 800; letter-spacing: 0.5px; color: #ffffff; line-height: 1.2;">
                            SAGAR <span style="color: #38bdf8;">STARTER\'S</span>
                        </div>
                        <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 3px;">
                            Industrial &amp; Agricultural Starters
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                        <span style="display: inline-block; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 3px 9px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                            ✓ Verified Order
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hero Confirmation Banner -->
        <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 22px 16px; text-align: center; color: #ffffff;">
            <div style="display: inline-block; width: 44px; height: 44px; line-height: 42px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 22px; margin-bottom: 8px; border: 2px solid rgba(255, 255, 255, 0.35);">
                ✓
            </div>
            <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.4px;">Order Confirmed!</h2>
            <p style="margin: 0; font-size: 13px; color: #e0f2fe; line-height: 1.4;">Thank you for your purchase. We are preparing your order for dispatch.</p>
        </div>

        <!-- Main Content -->
        <div style="padding: 18px 14px; box-sizing: border-box;">
            <p style="font-size: 14px; color: #1e293b; margin: 0 0 10px;">
                Hello <strong>{customer_name}</strong>,
            </p>
            <p style="font-size: 13px; color: #475569; margin: 0 0 16px; line-height: 1.55;">
                We are pleased to confirm your order details below. Our technical team is inspecting and packing your unit with utmost care. You will receive live courier tracking as soon as it ships.
            </p>

            <!-- Order Highlights Grid -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; box-sizing: border-box;">
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Order Number</span>
                        <strong style="font-size: 15px; color: #0284c7;">#{order_id}</strong>
                    </td>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Order Date</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 600; line-height: 1.3; display: block;">{date_str}</span>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Payment Method</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 600; line-height: 1.3; display: block;">{payment_method}</span>
                    </td>
                    <td width="50%" style="padding: 10px 10px; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Order Status</span>
                        <span style="display: inline-block; background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; line-height: 1.3;">
                            Pending / In Progress
                        </span>
                    </td>
                </tr>
            </table>

            <!-- Order Items Section -->
            <div style="margin-bottom: 20px; box-sizing: border-box; width: 100%; overflow: hidden;">
                <div style="font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                    📦 Order Summary
                </div>
                {items_table}
            </div>

            <!-- Call To Actions -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0 10px; width: 100%;">
                <tr>
                    <td align="center" style="padding: 0;">
                        <a href="https://wa.me/918573934013?text=Hi%20Sagar%20Starters,%20I%20have%20a%20query%20about%20Order%20%23{order_id}" style="display: inline-block; background-color: #25d366; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 11px 24px; border-radius: 50px; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35); box-sizing: border-box; max-width: 90%;">
                            💬 WhatsApp Support
                        </a>
                    </td>
                </tr>
            </table>

            <!-- Guarantee Box -->
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; margin-top: 16px; box-sizing: border-box;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                    <tr>
                        <td width="26" style="vertical-align: top; font-size: 16px;">🛡️</td>
                        <td style="padding-left: 6px; vertical-align: top;">
                            <div style="font-size: 12px; font-weight: 700; color: #166534;">Genuine Manufacturer Assurance</div>
                            <div style="font-size: 11px; color: #15803d; margin-top: 2px; line-height: 1.4;">All motor starters are 100% factory inspected and tested. Have questions? Reply directly to this email.</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div style="background-color: #0f172a; padding: 16px 14px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px solid #1e293b;">
            <p style="margin: 0 0 6px; font-size: 12px; font-weight: 700; color: #f8fafc;">Sagar Starter\'s Support Team</p>
            <p style="margin: 0 0 8px; color: #64748b; line-height: 1.4;">
                Email: <a href="mailto:sagarstarters@gmail.com" style="color: #38bdf8; text-decoration: none;">sagarstarters@gmail.com</a> &nbsp;|&nbsp; Phone: <a href="tel:+918573934013" style="color: #38bdf8; text-decoration: none;">+91 85739 34013</a>
            </p>
            <p style="margin: 0; font-size: 10px; color: #475569;">
                &copy; {current_year} Sagar Starter\'s. All rights reserved.
            </p>
        </div>
    </div>
</div>';

    // Canonical executive layout for Admin Order Notification (Mobile-Optimized)
    $exec_admin_body = '<div style="background-color: #f1f5f9; padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; box-sizing: border-box; -webkit-text-size-adjust: 100%;">
    <!-- mobile-responsive-v2 -->
    <div style="max-width: 600px; width: 100%; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.05); line-height: 1.5; box-sizing: border-box;">
        <!-- Top Admin Bar -->
        <div style="background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); padding: 14px 14px; text-align: left; border-bottom: 1px solid #047857;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td style="vertical-align: middle; text-align: left;">
                        <div style="font-size: 15px; font-weight: 800; color: #ffffff; line-height: 1.2;">
                            SAGAR <span style="color: #34d399;">STARTER\'S</span> ADMIN
                        </div>
                        <div style="font-size: 10px; color: #a7f3d0; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 3px;">
                            New Order Placement Alert
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                        <span style="display: inline-block; background: rgba(52, 211, 153, 0.2); border: 1px solid #34d399; color: #ffffff; padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                            ⚡ Action Required
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Alert Hero Banner -->
        <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); padding: 20px 16px; text-align: center; color: #ffffff;">
            <div style="display: inline-block; width: 42px; height: 42px; line-height: 40px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 20px; margin-bottom: 6px; border: 2px solid rgba(255, 255, 255, 0.35);">
                🛒
            </div>
            <h2 style="margin: 0 0 4px; font-size: 21px; font-weight: 800; color: #ffffff;">New Order Received!</h2>
            <p style="margin: 0; font-size: 13px; color: #d1fae5; line-height: 1.4;">Order #{order_id} has been placed and requires fulfillment.</p>
        </div>

        <!-- Main Content -->
        <div style="padding: 18px 14px; box-sizing: border-box;">
            <!-- Order Meta Grid -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; box-sizing: border-box;">
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Order ID</span>
                        <strong style="font-size: 15px; color: #059669;">#{order_id}</strong>
                    </td>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Total Amount</span>
                        <strong style="font-size: 15px; color: #0f172a; white-space: nowrap;">{total_amount}</strong>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Customer Name</span>
                        <strong style="font-size: 13px; color: #1e293b; display: block; word-break: break-word;">{customer_name}</strong>
                    </td>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-break: break-all; overflow-wrap: anywhere; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Customer Email</span>
                        <a href="mailto:{customer_email}" style="font-size: 11px; color: #0284c7; text-decoration: none; font-weight: 600; word-break: break-all; overflow-wrap: anywhere; display: inline-block; line-height: 1.3;">{customer_email}</a>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Date &amp; Time</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 500; line-height: 1.3; display: block;">{date_str}</span>
                    </td>
                    <td width="50%" style="padding: 10px 10px; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Payment Method</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 600; line-height: 1.3; display: block;">{payment_method}</span>
                    </td>
                </tr>
            </table>

            <!-- Ordered Items -->
            <div style="margin-bottom: 20px; box-sizing: border-box; width: 100%; overflow: hidden;">
                <div style="font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                    📋 Ordered Products
                </div>
                {items_table}
            </div>

            <!-- Admin Action Buttons -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 20px; width: 100%;">
                <tr>
                    <td align="center" style="padding: 0;">
                        <a href="{admin_order_url}" style="display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 11px 20px; border-radius: 50px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35); margin: 4px; box-sizing: border-box;">
                            ⚙️ View in Admin Panel &rarr;
                        </a>
                        <a href="mailto:{customer_email}?subject=Order%20%23{order_id}%20Update" style="display: inline-block; background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 50px; margin: 4px; box-sizing: border-box;">
                            ✉️ Email Customer
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div style="background-color: #f8fafc; padding: 14px 14px; text-align: center; color: #64748b; font-size: 11px; border-top: 1px solid #e2e8f0;">
            Automated store notification for Sagar Starter\'s Administrators.
        </div>
    </div>
</div>';

    // --- 1. SEND CUSTOMER EMAIL ---
    if ($customer_email) {
        try {
            $customer_mail = getMailerInstance();
            $customer_mail->addAddress($customer_email, $customer_name);
            $customer_mail->isHTML(true);

            // Fetch template
            $tpl = getEmailTemplate($conn, 'order_confirmation_customer');
            
            // Check if template in DB is legacy, missing, or lacks mobile-responsive optimizations (v2)
            if (!$tpl || empty($tpl['body']) || strpos($tpl['body'], 'mobile-responsive-v2') === false || strpos($tpl['body'], 'Order Instructions') !== false || strpos($tpl['body'], 'background-color: #0d6efd; padding: 20px;') !== false) {
                if ($conn) {
                    $conn->query("UPDATE email_templates SET body = '" . $conn->real_escape_string($exec_customer_body) . "' WHERE tpl_key = 'order_confirmation_customer'");
                }
                $cust_subject = !empty($tpl['subject']) ? $tpl['subject'] : "Your Order #{order_id} Has Been Confirmed";
                $cust_body = $exec_customer_body;
            } else {
                $cust_subject = $tpl['subject'];
                $cust_body = $tpl['body'];
            }

            $vars = [
                'customer_name' => htmlspecialchars($customer_name),
                'order_id' => $order_id,
                'date_str' => $date_str,
                'payment_method' => $payment_text,
                'total_amount' => $currency . number_format($subtotal, 2),
                'items_table' => $items_html,
                'site_url' => $site_url,
                'current_year' => date('Y')
            ];

            $customer_mail->Subject = parseTemplate($cust_subject, $vars);
            $customer_mail->Body = parseTemplate($cust_body, $vars);
            $customer_mail->send();
            logEmailAttempt($conn, $order_id, $customer_email, 'customer_order', 'success');
            
        } catch (Exception $e) {
            logEmailAttempt($conn, $order_id, $customer_email, 'customer_order', 'failed', "PHPMailer Error: {$customer_mail->ErrorInfo}");
        }
    }
    
    // --- 2. SEND ADMIN EMAIL ---
    if ($admin_email) {
        try {
            $admin_mail = getMailerInstance();
            $admin_mail->addAddress($admin_email, 'Store Administrator');
            $admin_mail->addReplyTo($customer_email ?? SMTP_USER, $customer_name);
            $admin_mail->isHTML(true);

            // Fetch template
            $tpl = getEmailTemplate($conn, 'order_confirmation_admin');
            
            // Check if template in DB is legacy, missing, or lacks mobile-responsive optimizations (v2)
            if (!$tpl || empty($tpl['body']) || strpos($tpl['body'], 'mobile-responsive-v2') === false || strpos($tpl['body'], 'background-color: #198754;') !== false || strpos($tpl['body'], 'Ordered Products</h3>') !== false) {
                if ($conn) {
                    $conn->query("UPDATE email_templates SET body = '" . $conn->real_escape_string($exec_admin_body) . "' WHERE tpl_key = 'order_confirmation_admin'");
                }
                $admin_subject = !empty($tpl['subject']) ? $tpl['subject'] : "New Order Received – Order #{order_id}";
                $admin_body = $exec_admin_body;
            } else {
                $admin_subject = $tpl['subject'];
                $admin_body = $tpl['body'];
            }

            $vars = [
                'order_id' => $order_id,
                'customer_name' => htmlspecialchars($customer_name),
                'customer_email' => htmlspecialchars($customer_email),
                'date_str' => $date_str,
                'payment_method' => $payment_text,
                'total_amount' => $currency . number_format($subtotal, 2),
                'items_table' => $items_html,
                'admin_order_url' => $admin_order_url,
                'site_url' => $site_url,
                'current_year' => date('Y')
            ];

            $admin_mail->Subject = parseTemplate($admin_subject, $vars);
            $admin_mail->Body = parseTemplate($admin_body, $vars);
            $admin_mail->send();
            logEmailAttempt($conn, $order_id, $admin_email, 'admin_order', 'success');
            
        } catch (Exception $e) {
            logEmailAttempt($conn, $order_id, $admin_email, 'admin_order', 'failed', "PHPMailer Error: {$admin_mail->ErrorInfo}");
        }
    }
}

/**
 * Send Order Status Update Email (Customer)
 */
function sendOrderStatusEmail($conn, $order_id, $customer_email, $customer_name, $new_status) {
    
    // 1. Check if emails are enabled globally
    $settings_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_email_notifications'");
    $emails_enabled = $settings_q->fetch_assoc()['setting_value'] ?? '0';
    
    if ($emails_enabled !== '1') {
        return false; // Emails are disabled
    }

    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        logEmailAttempt($conn, $order_id, $customer_email, 'status_update', 'failed', 'Invalid customer email address format.');
        return false;
    }

    // Format the status for display
    $display_status = ucwords(str_replace('_', ' ', $new_status));
    
    // Custom message, icon and color based on status
    $status_message = "";
    $color_code = "#0284c7"; // Default sky blue
    $status_icon = "🔔";
    
    switch ($new_status) {
        case 'processing':
            $status_message = "Your order is now being processed by our warehouse team and will be prepared for shipping shortly.";
            $color_code = "#0284c7"; // Blue
            $status_icon = "⚙️";
            break;
        case 'partially_shipped':
            $status_message = "Your order has been partially shipped. Some items are on the way, and the remainder will follow soon.";
            $color_code = "#d97706"; // Amber
            $status_icon = "📦";
            break;
        case 'shipped':
            $status_message = "Great news! Your complete order has been shipped and is on its way to you.";
            $color_code = "#059669"; // Emerald green
            $status_icon = "🚚";
            break;
        case 'delivered':
            $status_message = "Your order has been marked as delivered. We hope you enjoy your purchase!";
            $color_code = "#16a34a"; // Success green
            $status_icon = "🎉";
            break;
        case 'cancelled':
            $status_message = "Your order has been cancelled. If you have already been charged, a refund will be processed according to our policy.";
            $color_code = "#dc2626"; // Crimson red
            $status_icon = "❌";
            break;
        default:
            $status_message = "The status of your order has been updated to: " . $display_status;
            $color_code = "#0284c7";
            $status_icon = "🔔";
            break;
    }

    // Resolve base site url and tracking link
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $site_url = defined('SITE_URL') && !empty(SITE_URL) ? rtrim(SITE_URL, '/') : ($proto . $host . '/SagarSite');
    if (!preg_match('~^https?://~i', $site_url)) {
        $site_url = $proto . $host . '/' . ltrim($site_url, '/');
    }
    $order_link = $site_url . '/track_order.php';

    // Fetch tracking & order metadata safely
    $date_str = date('d M Y, h:i A');
    $tracking_info = "Available once dispatched";
    $payment_method = "Standard";
    $total_amount = "";
    
    $ord_q = $conn->query("SELECT created_at, payment_method, total_amount, carrier, tracking_number FROM orders WHERE id = " . intval($order_id));
    if ($ord_q && $ord_row = $ord_q->fetch_assoc()) {
        if (!empty($ord_row['payment_method'])) {
            $payment_method = strtoupper($ord_row['payment_method']);
        }
        if (!empty($ord_row['total_amount'])) {
            $total_amount = '₹' . number_format($ord_row['total_amount'], 2);
        }
        if (!empty($ord_row['tracking_number'])) {
            $tracking_info = (!empty($ord_row['carrier']) ? htmlspecialchars($ord_row['carrier']) . ' - ' : '') . '#' . htmlspecialchars($ord_row['tracking_number']);
        }
    }
    
    // Check order_tracking table if tracking still not found
    if ($tracking_info === "Available once dispatched") {
        try {
            $trk_q = $conn->query("SELECT ot.tracking_number, cc.name AS courier_name FROM order_tracking ot LEFT JOIN courier_companies cc ON ot.courier_id = cc.id WHERE ot.order_id = " . intval($order_id) . " ORDER BY ot.id DESC LIMIT 1");
            if ($trk_q && $trk_row = $trk_q->fetch_assoc()) {
                if (!empty($trk_row['tracking_number'])) {
                    $tracking_info = (!empty($trk_row['courier_name']) ? htmlspecialchars($trk_row['courier_name']) . ' - ' : '') . '#' . htmlspecialchars($trk_row['tracking_number']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if tracking tables not present
        }
    }

    // Canonical executive layout for Order Status Update (Mobile-Optimized)
    $exec_status_update_body = '<div style="background-color: #f1f5f9; padding: 16px 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; box-sizing: border-box; -webkit-text-size-adjust: 100%;">
    <!-- mobile-responsive-v2 -->
    <div style="max-width: 600px; width: 100%; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.05); line-height: 1.5; box-sizing: border-box;">
        <!-- Top Brand Bar -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 16px 14px; text-align: left; border-bottom: 1px solid #334155;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td style="vertical-align: middle; text-align: left;">
                        <div style="font-size: 16px; font-weight: 800; letter-spacing: 0.5px; color: #ffffff; line-height: 1.2;">
                            SAGAR <span style="color: #38bdf8;">STARTER\'S</span>
                        </div>
                        <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 3px;">
                            Industrial &amp; Agricultural Starters
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                        <span style="display: inline-block; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 3px 9px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                            ⚡ Status Updated
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hero Status Banner -->
        <div style="background: {status_color}; background: linear-gradient(135deg, {status_color} 0%, #0f172a 100%); padding: 22px 16px; text-align: center; color: #ffffff;">
            <div style="display: inline-block; width: 44px; height: 44px; line-height: 42px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 22px; margin-bottom: 8px; border: 2px solid rgba(255, 255, 255, 0.35);">
                {status_icon}
            </div>
            <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.4px;">Order Status: {display_status}</h2>
            <p style="margin: 0; font-size: 13px; color: #f8fafc; line-height: 1.4;">Your order #{order_id} has been updated to <strong>{display_status}</strong>.</p>
        </div>

        <!-- Main Content -->
        <div style="padding: 18px 14px; box-sizing: border-box;">
            <p style="font-size: 14px; color: #1e293b; margin: 0 0 10px;">
                Hello <strong>{customer_name}</strong>,
            </p>
            <p style="font-size: 13px; color: #475569; margin: 0 0 16px; line-height: 1.55;">
                We are writing to let you know that the current status of your order has been updated. Here is the latest progress on your purchase:
            </p>

            <!-- Status Notice Callout Box -->
            <div style="background-color: #f8fafc; border-left: 4px solid {status_color}; border-radius: 6px; padding: 12px 14px; margin-bottom: 20px; box-sizing: border-box;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: {status_color}; margin-bottom: 3px;">
                                Latest Update
                            </div>
                            <div style="font-size: 13px; color: #1e293b; font-weight: 600; line-height: 1.45;">
                                {status_message}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Order Highlights Grid -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; box-sizing: border-box;">
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Order Number</span>
                        <strong style="font-size: 15px; color: #0284c7;">#{order_id}</strong>
                    </td>
                    <td width="50%" style="padding: 10px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Current Status</span>
                        <span style="display: inline-block; background-color: #ffffff; color: {status_color}; border: 1px solid {status_color}; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; line-height: 1.3;">
                            ● {display_status}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="padding: 10px 10px; border-right: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Updated Date</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 600; line-height: 1.3; display: block;">{date_str}</span>
                    </td>
                    <td width="50%" style="padding: 10px 10px; vertical-align: top; word-break: break-word; box-sizing: border-box;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;">Tracking / Courier</span>
                        <span style="font-size: 12px; color: #1e293b; font-weight: 600; line-height: 1.3; display: block; word-break: break-word;">{tracking_info}</span>
                    </td>
                </tr>
            </table>

            <!-- Call To Actions -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0 10px; width: 100%;">
                <tr>
                    <td align="center" style="padding: 0;">
                        <a href="{order_link}" style="display: inline-block; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 11px 22px; border-radius: 50px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); margin: 4px; box-sizing: border-box;">
                            📦 Track Live Status &rarr;
                        </a>
                        <a href="https://wa.me/918573934013?text=Hi%20Sagar%20Starters,%20I%20have%20a%20query%20about%20Order%20%23{order_id}" style="display: inline-block; background-color: #25d366; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 11px 22px; border-radius: 50px; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35); margin: 4px; box-sizing: border-box;">
                            💬 WhatsApp Support
                        </a>
                    </td>
                </tr>
            </table>

            <!-- Guarantee Box -->
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; margin-top: 16px; box-sizing: border-box;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                    <tr>
                        <td width="26" style="vertical-align: top; font-size: 16px;">🛡️</td>
                        <td style="padding-left: 6px; vertical-align: top;">
                            <div style="font-size: 12px; font-weight: 700; color: #166534;">Genuine Manufacturer Assurance</div>
                            <div style="font-size: 11px; color: #15803d; margin-top: 2px; line-height: 1.4;">All motor starters are 100% factory inspected and tested. Have questions? Reply directly to this email.</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div style="background-color: #0f172a; padding: 16px 14px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px solid #1e293b;">
            <p style="margin: 0 0 6px; font-size: 12px; font-weight: 700; color: #f8fafc;">Sagar Starter\'s Support Team</p>
            <p style="margin: 0 0 8px; color: #64748b; line-height: 1.4;">
                Email: <a href="mailto:sagarstarters@gmail.com" style="color: #38bdf8; text-decoration: none;">sagarstarters@gmail.com</a> &nbsp;|&nbsp; Phone: <a href="tel:+918573934013" style="color: #38bdf8; text-decoration: none;">+91 85739 34013</a>
            </p>
            <p style="margin: 0; font-size: 10px; color: #475569;">
                &copy; {current_year} Sagar Starter\'s. All rights reserved.
            </p>
        </div>
    </div>
</div>';

    try {
        $mail = getMailerInstance();
        $mail->addAddress($customer_email, $customer_name);
        $mail->isHTML(true);

        // Auto-upgrade database templates if they are missing or have legacy cut-off styles
        $tpl = getEmailTemplate($conn, 'order_status_update');
        if (!$tpl || empty($tpl['body']) || strpos($tpl['body'], 'mobile-responsive-v2') === false) {
            $upd_stmt = $conn->prepare("UPDATE email_templates SET body = ?, placeholders = ? WHERE tpl_key = 'order_status_update'");
            if ($upd_stmt) {
                $placeholders_str = '{status_color}, {status_icon}, {customer_name}, {display_status}, {status_message}, {order_id}, {date_str}, {tracking_info}, {order_link}, {site_url}, {total_amount}, {payment_method}, {current_year}';
                $upd_stmt->bind_param("ss", $exec_status_update_body, $placeholders_str);
                $upd_stmt->execute();
                $upd_stmt->close();
                $tpl = getEmailTemplate($conn, 'order_status_update');
            }
        }

        $vars = [
            'status_color'   => $color_code,
            'status_icon'    => $status_icon,
            'customer_name'  => htmlspecialchars($customer_name),
            'display_status' => $display_status,
            'status_message' => $status_message,
            'order_id'       => $order_id,
            'date_str'       => $date_str,
            'tracking_info'  => $tracking_info,
            'order_link'     => $order_link,
            'site_url'       => $site_url,
            'total_amount'   => $total_amount,
            'payment_method' => $payment_method,
            'current_year'   => date('Y')
        ];

        if ($tpl) {
            $mail->Subject = parseTemplate($tpl['subject'], $vars);
            $mail->Body = parseTemplate($tpl['body'], $vars);
        } else {
            // Fallback
            $mail->Subject = "Update on your Order #{$order_id} - {$display_status}";
            $mail->Body = parseTemplate($exec_status_update_body, $vars);
        }

        $mail->send();
        
        logEmailAttempt($conn, $order_id, $customer_email, 'status_update', 'success');
        return true;
        
    } catch (Exception $e) {
        logEmailAttempt($conn, $order_id, $customer_email, 'status_update', 'failed', "PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send a generic HTML email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body HTML email body
 * @return array ['success' => boolean, 'error' => string]
 */
function sendEmail($to, $subject, $body) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid recipient email address.'];
    }

    try {
        $mail = getMailerInstance();
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>
