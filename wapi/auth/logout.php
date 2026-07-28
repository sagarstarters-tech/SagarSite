<?php
/**
 * WAPI SaaS - Logout Handler
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$auth = new Auth();
$auth->logout();

setFlash('success', 'You have been logged out successfully.');
redirect('auth/login.php');
