<?php
/**
 * WAPI SaaS Platform - Session Management
 * Secure session handling with regeneration and validation
 */

// Start secure session
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session
        ini_set('session.name', SESSION_NAME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.cookie_httponly', SESSION_HTTPONLY);
        ini_set('session.cookie_secure', SESSION_SECURE);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);

        session_start();

        // Regenerate session ID periodically
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }

        // Validate session fingerprint
        $fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
            session_destroy();
            session_start();
        }
    }
}

// Initialize session on include
initSession();
