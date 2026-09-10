<?php
/**
 * WhatsApp Diagnostic Tool Redirect
 *
 * Standalone root diagnostic script has been consolidated into the
 * secure, authenticated Admin Panel tool: /admin/whatsapp_debug.php
 */

include_once __DIR__ . '/includes/session_setup.php';

// Auth check: Must be logged-in admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin/admin_login.php");
    exit;
}

header("Location: admin/whatsapp_debug.php", true, 302);
exit;
