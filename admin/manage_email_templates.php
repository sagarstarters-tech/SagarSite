<?php
require_once 'admin_header.php';

// --- DATABASE INITIALIZATION (Ensures table exists and has defaults) ---
$conn->query("CREATE TABLE IF NOT EXISTS `email_templates` (
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

// check/insert defaults
$defaults = [
    ['signup_verification', 'Signup Verification', 'Verify Your Account at Sagar Starter\'s', "<h2>Welcome to Sagar Starter's!</h2>\n<p>Dear {name},</p>\n<p>Thank you for registering. Please click the link below to verify your email address:</p>\n<p><a href='{verify_link}'>{verify_link}</a></p>\n<br>\n<p>If you didn't request this, ignore this email.</p>", '{name}, {verify_link}'],
    ['password_reset', 'Password Reset Request', 'Password Reset Request', "<h3>Password Reset</h3>\n<p>Hi {name},</p>\n<p>You requested a password reset. Click the link below to set a new password. This link will expire in 1 hour.</p>\n<p><a href='{reset_link}'>{reset_link}</a></p>\n<p>If you didn't request this, you can safely ignore this email.</p>", '{name}, {reset_link}'],
    ['order_confirmation_customer', 'Order Confirmation (Customer)', 'Your Order #{order_id} Has Been Confirmed', "<div style=\"background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\">
    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); line-height: 1.5;\">
        <!-- Top Brand Bar -->
        <div style=\"background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 20px 25px; text-align: left; border-bottom: 1px solid #334155;\">
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                <tr>
                    <td style=\"vertical-align: middle;\">
                        <div style=\"font-size: 18px; font-weight: 800; letter-spacing: 0.5px; color: #ffffff;\">
                            SAGAR <span style=\"color: #38bdf8;\">STARTER'S</span>
                        </div>
                        <div style=\"font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;\">
                            Industrial & Agricultural Starters
                        </div>
                    </td>
                    <td style=\"text-align: right; vertical-align: middle;\">
                        <span style=\"display: inline-block; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;\">
                            ✓ Verified Order
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hero Confirmation Banner -->
        <div style=\"background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px 25px; text-align: center; color: #ffffff;\">
            <div style=\"display: inline-block; width: 50px; height: 50px; line-height: 48px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 24px; margin-bottom: 10px; border: 2px solid rgba(255, 255, 255, 0.35);\">
                ✓
            </div>
            <h2 style=\"margin: 0 0 6px; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;\">Order Confirmed!</h2>
            <p style=\"margin: 0; font-size: 14px; color: #e0f2fe;\">Thank you for your purchase. We are preparing your order for dispatch.</p>
        </div>

        <!-- Main Content -->
        <div style=\"padding: 26px;\">
            <p style=\"font-size: 15px; color: #1e293b; margin: 0 0 12px;\">
                Hello <strong>{customer_name}</strong>,
            </p>
            <p style=\"font-size: 14px; color: #475569; margin: 0 0 20px; line-height: 1.6;\">
                We are pleased to confirm your order details below. Our technical team is inspecting and packing your unit with utmost care. You will receive live courier tracking as soon as it ships.
            </p>

            <!-- Order Highlights Grid -->
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 24px; overflow: hidden;\">
                <tr>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Number</span>
                        <strong style=\"font-size: 16px; color: #0284c7;\">#{order_id}</strong>
                    </td>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Date</span>
                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{date_str}</span>
                    </td>
                </tr>
                <tr>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-right: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Payment Method</span>
                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{payment_method}</span>
                    </td>
                    <td width=\"50%\" style=\"padding: 13px 16px;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Status</span>
                        <span style=\"display: inline-block; background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 12px;\">
                            Pending / In Progress
                        </span>
                    </td>
                </tr>
            </table>

            <!-- Order Items Section -->
            <div style=\"margin-bottom: 24px;\">
                <div style=\"font-size: 13px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;\">
                    📦 Order Summary
                </div>
                {items_table}
            </div>

            <!-- Call To Actions -->
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin: 24px 0 10px;\">
                <tr>
                    <td align=\"center\">
                        <a href=\"https://wa.me/918573934013?text=Hi%20Sagar%20Starters,%20I%20have%20a%20query%20about%20Order%20%23{order_id}\" style=\"display: inline-block; background-color: #25d366; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 50px; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);\">
                            💬 WhatsApp Support
                        </a>
                    </td>
                </tr>
            </table>

            <!-- Guarantee Box -->
            <div style=\"background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-top: 20px;\">
                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                    <tr>
                        <td width=\"28\" style=\"vertical-align: top; font-size: 18px;\">🛡️</td>
                        <td style=\"padding-left: 8px; vertical-align: top;\">
                            <div style=\"font-size: 13px; font-weight: 700; color: #166534;\">Genuine Manufacturer Assurance</div>
                            <div style=\"font-size: 12px; color: #15803d; margin-top: 2px;\">All motor starters are 100% factory inspected and tested. Have questions? Reply directly to this email.</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div style=\"background-color: #0f172a; padding: 20px 25px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #1e293b;\">
            <p style=\"margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #f8fafc;\">Sagar Starter's Support Team</p>
            <p style=\"margin: 0 0 10px; color: #64748b;\">
                Email: <a href=\"mailto:sagarstarters@gmail.com\" style=\"color: #38bdf8; text-decoration: none;\">sagarstarters@gmail.com</a> &nbsp;|&nbsp; Phone: <a href=\"tel:+918573934013\" style=\"color: #38bdf8; text-decoration: none;\">+91 85739 34013</a>
            </p>
            <p style=\"margin: 0; font-size: 11px; color: #475569;\">
                &copy; {current_year} Sagar Starter's. All rights reserved.
            </p>
        </div>
    </div>
</div>", '{customer_name}, {order_id}, {date_str}, {payment_method}, {items_table}, {total_amount}, {site_url}, {current_year}'],
    ['order_confirmation_admin', 'Order Notification (Admin)', 'New Order Received – Order #{order_id}', "<div style=\"background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\">
    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); line-height: 1.5;\">
        <!-- Top Admin Bar -->
        <div style=\"background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); padding: 18px 25px; text-align: left; border-bottom: 1px solid #047857;\">
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                <tr>
                    <td style=\"vertical-align: middle;\">
                        <div style=\"font-size: 17px; font-weight: 800; color: #ffffff;\">
                            SAGAR <span style=\"color: #34d399;\">STARTER'S</span> ADMIN
                        </div>
                        <div style=\"font-size: 11px; color: #a7f3d0; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;\">
                            New Order Placement Alert
                        </div>
                    </td>
                    <td style=\"text-align: right; vertical-align: middle;\">
                        <span style=\"display: inline-block; background: rgba(52, 211, 153, 0.2); border: 1px solid #34d399; color: #ffffff; padding: 3px 11px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;\">
                            ⚡ Action Required
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Alert Hero Banner -->
        <div style=\"background: linear-gradient(135deg, #059669 0%, #047857 100%); padding: 26px 25px; text-align: center; color: #ffffff;\">
            <div style=\"display: inline-block; width: 46px; height: 46px; line-height: 44px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 22px; margin-bottom: 8px; border: 2px solid rgba(255, 255, 255, 0.35);\">
                🛒
            </div>
            <h2 style=\"margin: 0 0 4px; font-size: 23px; font-weight: 800; color: #ffffff;\">New Order Received!</h2>
            <p style=\"margin: 0; font-size: 14px; color: #d1fae5;\">Order #{order_id} has been placed and requires fulfillment.</p>
        </div>

        <!-- Main Content -->
        <div style=\"padding: 26px;\">
            <!-- Order Meta Grid -->
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 24px; overflow: hidden;\">
                <tr>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order ID</span>
                        <strong style=\"font-size: 16px; color: #059669;\">#{order_id}</strong>
                    </td>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Total Amount</span>
                        <strong style=\"font-size: 16px; color: #0f172a;\">{total_amount}</strong>
                    </td>
                </tr>
                <tr>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Customer Name</span>
                        <strong style=\"font-size: 14px; color: #1e293b;\">{customer_name}</strong>
                    </td>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Customer Email</span>
                        <a href=\"mailto:{customer_email}\" style=\"font-size: 13px; color: #0284c7; text-decoration: none; font-weight: 600;\">{customer_email}</a>
                    </td>
                </tr>
                <tr>
                    <td width=\"50%\" style=\"padding: 13px 16px; border-right: 1px solid #e2e8f0;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Date & Time</span>
                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 500;\">{date_str}</span>
                    </td>
                    <td width=\"50%\" style=\"padding: 13px 16px;\">
                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Payment Method</span>
                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{payment_method}</span>
                    </td>
                </tr>
            </table>

            <!-- Ordered Items -->
            <div style=\"margin-bottom: 24px;\">
                <div style=\"font-size: 13px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;\">
                    📋 Ordered Products
                </div>
                {items_table}
            </div>

            <!-- Admin Action Buttons -->
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin-top: 24px;\">
                <tr>
                    <td align=\"center\">
                        <a href=\"{admin_order_url}\" style=\"display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 50px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35); margin: 4px;\">
                            ⚙️ View in Admin Panel &rarr;
                        </a>
                        <a href=\"mailto:{customer_email}?subject=Order%20%23{order_id}%20Update\" style=\"display: inline-block; background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 22px; border-radius: 50px; margin: 4px;\">
                            ✉️ Email Customer
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div style=\"background-color: #f8fafc; padding: 16px 25px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0;\">
            Automated store notification for Sagar Starter's Administrators.
        </div>
    </div>
</div>", '{order_id}, {customer_name}, {customer_email}, {date_str}, {payment_method}, {total_amount}, {items_table}, {admin_order_url}, {site_url}, {current_year}'],
    ['order_status_update', 'Order Status Update', 'Update on your Order #{order_id} - {display_status}', "\n<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333; border: 1px solid #eaeaea; border-radius: 8px; overflow: hidden;\">\n    <div style=\"background-color: {status_color}; padding: 20px; text-align: center; color: white;\">\n        <h2 style=\"margin: 0;\">Order Status Update</h2>\n    </div>\n    <div style=\"padding: 20px;\">\n        <p style=\"font-size: 16px;\">Hello <strong>{customer_name}</strong>,</p>\n        \n        <div style=\"background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {status_color};\">\n            <h3 style=\"margin-top: 0; color: {status_color};\">Status: {display_status}</h3>\n            <p style=\"margin-bottom: 0;\">{status_message}</p>\n        </div>\n        \n        <p><strong>Order ID:</strong> #{order_id}</p>\n        \n        <p style=\"margin-top: 30px; font-size: 14px; color: #6c757d; text-align: center;\">\n            If you have any questions about your order, please reply to this email or contact our support team.\n        </p>\n    </div>\n    <div style=\"background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; border-top: 1px solid #eaeaea;\">\n        &copy; {current_year} Sagar Starter's. All rights reserved.\n    </div>\n</div>", '{status_color}, {customer_name}, {display_status}, {status_message}, {order_id}, {current_year}'],
    ['contact_form', 'Contact Us Submission', 'New Contact Form Submission: {subject}', "<h2>New Contact Form Submission</h2>\n<p><strong>Name:</strong> {name}</p>\n<p><strong>Email:</strong> {email}</p>\n<p><strong>Phone:</strong> {phone}</p>\n<p><strong>Subject:</strong> {subject}</p>\n<hr>\n<p><strong>Message:</strong></p>\n<p>{message}</p>", '{name}, {email}, {phone}, {subject}, {message}'],
    ['google_profile_reminder', 'Google Profile Completion Reminder', 'Complete Your Profile at {site_name} – Quick 1-Minute Setup', "\n<div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);\">\n    <div style=\"background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); padding: 32px 24px; text-align: center; color: #ffffff;\">\n        <div style=\"display: inline-block; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; padding: 12px; margin-bottom: 12px;\">\n            <img src=\"https://cdn-icons-png.flaticon.com/512/3135/3135715.png\" width=\"48\" height=\"48\" alt=\"Profile Icon\" style=\"vertical-align: middle;\">\n        </div>\n        <h2 style=\"margin: 0; font-size: 24px; font-weight: 700;\">Welcome to {site_name}!</h2>\n        <p style=\"margin: 6px 0 0; font-size: 15px; color: rgba(255,255,255,0.9);\">You are just one step away from seamless shopping & fast delivery.</p>\n    </div>\n    <div style=\"padding: 32px 28px; color: #334155; line-height: 1.6;\">\n        <p style=\"font-size: 16px; margin-top: 0;\">Hi <strong>{name}</strong>,</p>\n        <p style=\"font-size: 15px; margin-bottom: 20px;\">Thank you for signing in with Google! We noticed you moved away before finishing your shipping and contact details (Phone, Delivery Address, etc.).</p>\n        <div style=\"background-color: #f8fafc; border-left: 4px solid #0d6efd; padding: 16px 20px; border-radius: 6px; margin: 24px 0;\">\n            <p style=\"margin: 0 0 8px; font-weight: 600; color: #1e293b; font-size: 15px;\">Why complete your profile?</p>\n            <ul style=\"margin: 0; padding-left: 20px; color: #475569; font-size: 14px;\">\n                <li style=\"margin-bottom: 6px;\">⚡ <strong>Fast Checkout:</strong> Auto-fill your delivery details instantly.</li>\n                <li style=\"margin-bottom: 6px;\">📦 <strong>Live Order Tracking:</strong> Receive WhatsApp & SMS shipment updates.</li>\n                <li>🎁 <strong>Exclusive Offers:</strong> Access special member discounts.</li>\n            </ul>\n        </div>\n        <div style=\"text-align: center; margin: 32px 0 24px;\">\n            <a href=\"{profile_link}\" style=\"display: inline-block; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 14px 36px; border-radius: 50px; box-shadow: 0 4px 12px rgba(13,110,253,0.35);\">Complete My Profile Now &rarr;</a>\n        </div>\n        <p style=\"font-size: 13px; color: #64748b; text-align: center; margin-top: 20px;\">Or copy and paste this link in your browser:<br><a href=\"{profile_link}\" style=\"color: #0d6efd; word-break: break-all;\">{profile_link}</a></p>\n    </div>\n    <div style=\"background-color: #f1f5f9; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;\">\n        <p style=\"margin: 0 0 6px;\">This reminder was sent to <strong>{email}</strong> because you signed in to {site_name}.</p>\n        <p style=\"margin: 0;\">&copy; {current_year} {site_name}. All rights reserved.</p>\n    </div>\n</div>", '{name}, {email}, {profile_link}, {site_name}, {site_url}, {current_year}']
];

foreach ($defaults as $d) {
    if ($conn->query("SELECT id FROM email_templates WHERE tpl_key = '".$d[0]."' LIMIT 1")->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO email_templates (tpl_key, label, subject, body, placeholders) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $d[0], $d[1], $d[2], $d[3], $d[4]);
        $stmt->execute();
    }
}

// Fetch all templates
$res = $conn->query("SELECT * FROM email_templates ORDER BY label ASC");
$templates = [];
while ($row = $res->fetch_assoc()) {
    $templates[] = $row;
}

$success_msg = '';
$error_msg = '';
$editing_tpl = null;

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_template') {
        $id = (int)$_POST['id'];
        $subject = $_POST['subject'];
        $body = $_POST['body'];
        
        $stmt = $conn->prepare("UPDATE email_templates SET subject = ?, body = ? WHERE id = ?");
        $stmt->bind_param("ssi", $subject, $body, $id);
        
        if ($stmt->execute()) {
            $success_msg = "Template updated successfully.";
            $stmt->close();
            // Refresh templates info
            $res = $conn->query("SELECT * FROM email_templates ORDER BY label ASC");
            $templates = [];
            while ($row = $res->fetch_assoc()) {
                $templates[] = $row;
            }
        } else {
            $error_msg = "Failed to update template: " . $conn->error;
        }
    }
}

// Check if we're editing a specific template
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    foreach ($templates as $t) {
        if ((int)$t['id'] === $id) {
            $editing_tpl = $t;
            break;
        }
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="mb-0 text-dark fw-bold"><i class="fas fa-envelope-open-text me-2 text-primary"></i> Email Templates</h2>
        <p class="text-muted mb-0">Customize the emails sent to customers and administrators.</p>
    </div>
</div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
    <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
    <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- List Column -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white pt-4 pb-3">
                <h5 class="fw-bold m-0"><i class="fas fa-list me-2 text-secondary"></i> Select Template</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($templates as $t): ?>
                    <a href="?edit=<?php echo $t['id']; ?>" class="list-group-item list-group-item-action py-3 <?php echo ($editing_tpl && $editing_tpl['id'] == $t['id']) ? 'active' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($t['label']); ?></h6>
                        </div>
                        <small class="<?php echo ($editing_tpl && $editing_tpl['id'] == $t['id']) ? 'text-white-50' : 'text-muted'; ?>">
                            Key: <?php echo htmlspecialchars($t['tpl_key']); ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Edit Column -->
    <div class="col-md-8">
        <?php if ($editing_tpl): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white pt-4 pb-3">
                    <h5 class="fw-bold m-0"><i class="fas fa-edit me-2 text-primary"></i> Editing: <?php echo htmlspecialchars($editing_tpl['label']); ?></h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
    <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="id" value="<?php echo $editing_tpl['id']; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Email Subject</label>
                            <input type="text" name="subject" class="form-control bg-light" value="<?php echo htmlspecialchars($editing_tpl['subject']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Email Body (HTML supported)</label>
                            <textarea name="body" class="form-control bg-light" rows="15" required><?php echo htmlspecialchars($editing_tpl['body']); ?></textarea>
                            <div class="form-text mt-3">
                                <strong>Available Placeholders:</strong><br>
                                <div class="mt-2">
                                    <?php 
                                    $tags = explode(',', $editing_tpl['placeholders']);
                                    foreach ($tags as $tag): 
                                        $tag = trim($tag);
                                    ?>
                                        <code class="me-2 bg-white border px-2 py-1 rounded d-inline-block mb-2"><?php echo $tag; ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end border-top pt-4">
                            <button type="submit" class="btn btn-primary btn-custom px-5"><i class="fas fa-save me-2"></i>Save Template</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="card-body">
                    <i class="fas fa-mouse-pointer fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No Template Selected</h4>
                    <p class="text-muted px-5">Please select an email template from the list on the left to start editing its content.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
