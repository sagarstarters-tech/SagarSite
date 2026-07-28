<?php
/**
 * WhatsApp Chatbot Configuration (V2)
 * Integrated with the main application configuration.
 */

// Load main app configuration (this also loads .env)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// Engine Settings
if (!defined('WHATSAPP_API_VERSION')) define('WHATSAPP_API_VERSION', 'v18.0');

// Note: WHATSAPP_API_TOKEN and PHONE_NUMBER_ID are now handled dynamically in functions.php
// which fetches them from the database per account.

// Fallback for verification if not in main config (though it should be)
if (!defined('WEBHOOK_VERIFY_TOKEN')) {
    define('WEBHOOK_VERIFY_TOKEN', 'wapi_webhook_verify_token_2026');
}

// Log errors for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webhook_errors.log');
?>
