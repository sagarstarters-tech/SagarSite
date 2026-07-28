<?php
/**
 * CSRF Protection Class
 * Generates and validates CSRF tokens
 */
class CSRF {
    /**
     * Generate CSRF token
     */
    public static function generateToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Get hidden input field with CSRF token
     */
    public static function tokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Validate CSRF token
     */
    public static function validateToken($token = null) {
        if ($token === null) {
            $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
            return false;
        }

        $valid = hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
        return $valid;
    }

    /**
     * Validate and die if invalid
     */
    public static function validate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::validateToken()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']));
            }
        }
    }

    /**
     * Get meta tag for AJAX requests
     */
    public static function metaTag() {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}
