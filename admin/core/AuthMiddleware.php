<?php
/**
 * AuthMiddleware
 * Centralizes all admin authentication and session checks.
 * Replaces the inline auth logic that was in admin_header.php.
 */
class AuthMiddleware
{
    /**
     * Check if current user is authenticated as admin.
     * Redirects to login page if not authenticated.
     *
     * @param mysqli $conn   DB connection (optional, for profile_photo fallback)
     */
    public static function check($conn = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            include_once __DIR__ . '/../../includes/session_setup.php';
        }

        $current_page = basename($_SERVER['PHP_SELF']);

        // Skip auth check on the login page itself
        if ($current_page === 'admin_login.php') {
            return;
        }

        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: ' . self::getAdminBase() . 'admin_login.php');
            exit;
        }

        // 2. CSRF Check for Admin POST requests (only after auth is verified)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../helpers/csrf.php';
            csrf_verify();
        }

        // Always sync profile photo from DB to ensure latest image shows in navbar
        if ($conn !== null) {
            $uid = intval($_SESSION['user_id']);
            $stmt = $conn->prepare("SELECT profile_photo, google_avatar FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if ($res) {
                    $_SESSION['profile_photo'] = trim(($res['profile_photo'] ?? '') ?: ($res['google_avatar'] ?? ''));
                }
                $stmt->close();
            }
        }
    }

    /**
     * Returns the base admin URL path.
     */
    private static function getAdminBase(): string
    {
        // Works whether accessed from admin/ or a sub-directory
        $script_dir = dirname($_SERVER['PHP_SELF']);
        // Normalize so we always point to /admin/
        return rtrim($script_dir, '/') . '/';
    }

    /**
     * returns true if user is logged in as admin.
     */
    public static function isAdmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            include_once __DIR__ . '/../../includes/session_setup.php';
        }
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
    }
}
