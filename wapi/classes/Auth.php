<?php
/**
 * Auth Class - Authentication & Authorization
 * Handles login, registration, password reset, and role checking
 */
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Verify user email via token
     */
    public function verifyEmail($token) {
        $user = $this->db->fetch("SELECT id FROM users WHERE email_verify_token = ? AND email_verified = 0", [$token]);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or already used verification link.'];
        }

        $this->db->update('users', [
            'email_verified' => 1,
            'email_verify_token' => null
        ], 'id = ?', [$user['id']]);

        return ['success' => true, 'message' => 'Email verified successfully! You can now access all features.'];
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail($userId) {
        $user = $this->db->fetch("SELECT name, email, email_verified, email_verify_token FROM users WHERE id = ?", [$userId]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        if ($user['email_verified']) {
            return ['success' => false, 'message' => 'Email is already verified.'];
        }

        $verifyToken = $user['email_verify_token'];
        if (empty($verifyToken)) {
            $verifyToken = bin2hex(random_bytes(32));
            $this->db->update('users', ['email_verify_token' => $verifyToken], 'id = ?', [$userId]);
        }

        $siteName = setting('site_name', 'WAPI');
        $subject = "Verify your {$siteName} account";
        $verifyLink = APP_URL . "/auth/verify-email.php?token={$verifyToken}";
        
        $body = "<h2>Hello {$user['name']},</h2>
                <p>Please verify your email address to unlock all features on <strong>{$siteName}</strong>.</p>
                <div style='margin-top: 20px; margin-bottom: 20px;'>
                    <a href='{$verifyLink}' style='background-color: #6c63ff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Verify Email Address</a>
                </div>
                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p><a href='{$verifyLink}'>{$verifyLink}</a></p>
                <hr>
                <p style='font-size: 0.8rem; color: #999;'>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>";

        $mailResult = Mail::send($user['email'], $subject, $body);

        if (!$mailResult['success']) {
            return ['success' => false, 'message' => 'Failed to send verification email. Please try again.'];
        }

        return ['success' => true, 'message' => 'Verification email sent successfully! Please check your inbox.'];
    }

    /**
     * Register a new user
     */
    public function register($name, $email, $password, $phone = null, $company = null, $planSlug = '') {
        // Check if email already exists
        if ($this->db->exists('users', 'email = ?', [$email])) {
            return ['success' => false, 'message' => 'Email address already registered.'];
        }

        // Hash password
        $hashedPassword = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
        $uuid = $this->generateUUID();
        $verifyToken = bin2hex(random_bytes(32));

        try {
            $this->db->beginTransaction();

            // Create user
            $userId = $this->db->insert('users', [
                'uuid' => $uuid,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashedPassword,
                'company_name' => $company,
                'role' => 'user',
                'status' => 'active',
                'email_verify_token' => $verifyToken
            ]);

            $initialCredits = 0;

            // Initialize credits (0 if no plan selected)
            $this->db->insert('credits', [
                'user_id' => $userId,
                'total_credits' => $initialCredits,
                'used_credits' => 0
            ]);

            // Log activity
            $this->logActivity($userId, 'register', 'New user registered');

            $this->db->commit();

            // Send actual Welcome & Verification email
            $siteName = setting('site_name', 'WAPI');
            $subject = "Verify your {$siteName} account";
            $verifyLink = APP_URL . "/auth/verify-email.php?token={$verifyToken}";
            
            $body = "<h2>Hello {$name},</h2>
                    <p>Thank you for registering at <strong>{$siteName}</strong>.</p>
                    <p>To complete your registration and unlock all features, please verify your email address by clicking the button below.</p>
                    <div style='margin-top: 20px; margin-bottom: 20px;'>
                        <a href='{$verifyLink}' style='background-color: #6c63ff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Verify Email Address</a>
                    </div>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p><a href='{$verifyLink}'>{$verifyLink}</a></p>
                    <hr>
                    <p style='font-size: 0.8rem; color: #999;'>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>";

            Mail::send($email, $subject, $body);

            return [
                'success' => true,
                'message' => 'Registration successful!',
                'user_id' => $userId,
                'verify_token' => $verifyToken
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Registration Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Login user
     */
    public function login($email, $password) {
        $user = $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account locked. Try again in {$remaining} minutes."];
        }

        // Check account status
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account has been ' . $user['status'] . '.'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment login attempts
            $attempts = $user['login_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                $attempts = 0;
            }

            $this->db->update('users', [
                'login_attempts' => $attempts,
                'locked_until' => $lockUntil
            ], 'id = ?', [$user['id']]);

            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Successful login - reset attempts and set session
        $this->db->update('users', [
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['logged_in'] = true;

        // Regenerate session ID
        session_regenerate_id(true);

        // Log activity
        $this->logActivity($user['id'], 'login', 'User logged in');

        return [
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Require login - redirect if not authenticated
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . APP_URL . '/auth/login.php');
            exit;
        }
    }

    /**
     * Require active plan - redirect to subscription if no active plan
     */
    public static function requireActivePlan() {
        self::requireLogin();
        // Skip check if admin
        if (self::isAdmin()) return;
        
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $subscription = $db->fetch("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND expires_at > NOW()", [$userId]);
        
        if (!$subscription) {
            setFlash('warning', 'You must have an active plan to access this feature.');
            header('Location: ' . APP_URL . '/dashboard/subscription.php');
            exit;
        }
    }

    /**
     * Require WhatsApp setup - redirect if not configured
     */
    public static function requireWhatsAppSetup() {
        self::requireActivePlan();
        if (self::isAdmin()) return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $wa = $db->fetch("SELECT phone_number_id FROM whatsapp_accounts WHERE user_id = ?", [$userId]);
        
        if (empty($wa['phone_number_id'])) {
            setFlash('warning', 'You must complete your WhatsApp Cloud API setup to access this feature.');
            header('Location: ' . APP_URL . '/dashboard/whatsapp.php');
            exit;
        }
    }

    /**
     * Require admin - redirect if not admin
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . APP_URL . '/dashboard/');
            exit;
        }
    }

    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!self::isLoggedIn()) return null;
        return $this->db->fetch("SELECT id, uuid, name, email, phone, role, avatar, company_name, status, timezone, created_at FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    /**
     * Generate password reset token
     */
    public function forgotPassword($email) {
        $user = $this->db->fetch("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email]);
        if (!$user) {
            return ['success' => true, 'message' => 'If this email exists, a reset link has been sent.'];
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $this->db->update('users', [
            'reset_token' => $token,
            'reset_token_expiry' => $expiry
        ], 'id = ?', [$user['id']]);

        // Send actual email using the new Mail class
        $resetLink = APP_URL . "/auth/reset-password.php?token={$token}";
        $siteName = setting('site_name', 'WAPI');
        
        $subject = "Reset your {$siteName} password";
        $body = "<h2>Hello,</h2>
                <p>You requested to reset your password for your <strong>{$siteName}</strong> account.</p>
                <p>Please click the button below to set a new password. This link is valid for 1 hour.</p>
                <div style='margin-top: 20px;'>
                    <a href='{$resetLink}' style='background-color: #6c63ff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password</a>
                </div>
                <p style='margin-top: 30px; font-size: 0.8rem; color: #777;'>If you didn't request a password reset, you can safely ignore this email.</p>
                <hr>
                <p style='font-size: 0.8rem; color: #999;'>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>";

        $mailResult = Mail::send($email, $subject, $body);

        if (!$mailResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to send reset email: ' . $mailResult['message']
            ];
        }

        return [
            'success' => true,
            'message' => 'If this email exists, a reset link has been sent.',
            'token' => $token, // Still returning for debug/compatibility
            'mail_sent' => true
        ];
    }

    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()",
            [$token]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }

        $hashedPassword = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
        $this->db->update('users', [
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_token_expiry' => null
        ], 'id = ?', [$user['id']]);

        return ['success' => true, 'message' => 'Password has been reset successfully.'];
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        $allowed = ['name', 'phone', 'company_name', 'timezone', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No valid fields to update.'];
        }

        $this->db->update('users', $updateData, 'id = ?', [$userId]);
        
        // Update session if name changed
        if (isset($updateData['name'])) {
            $_SESSION['user_name'] = $updateData['name'];
        }
        if (isset($updateData['avatar'])) {
            $_SESSION['user_avatar'] = $updateData['avatar'];
        }

        return ['success' => true, 'message' => 'Profile updated successfully.'];
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);

        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $hashedPassword = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
        $this->db->update('users', ['password' => $hashedPassword], 'id = ?', [$userId]);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * Generate UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $action, $description) {
        $this->db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
