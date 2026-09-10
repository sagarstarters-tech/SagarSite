<?php
/**
 * manage_site_content.php — Backward Compatibility Redirect Stub
 *
 * All global site details, branding, footer content, contact info, and social links
 * have been unified in manage_admin_site.php?tab=site_details.
 */

include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

// Canonical consolidation: Redirect to unified site details tab
header("Location: manage_admin_site.php?tab=site_details", true, 302);
exit;
