<?php
/**
 * Admin Helper: CSRF Protection
 *
 * Usage:
 *   In every form: <?php echo csrf_input(); ?>
 *   On POST handling: csrf_verify();
 */

/**
 * Generate and store a CSRF token in session.
 * Returns the token string.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        include_once __DIR__ . '/../../includes/session_setup.php';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field.
 */
function csrf_input(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"_csrf_token\" value=\"{$token}\">";
}

/**
 * Verify the CSRF token from POST data.
 * Exits with 403 if invalid.
 */
function csrf_verify(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        include_once __DIR__ . '/../../includes/session_setup.php';
    }

    $submitted = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    // If session token was missing, generate one
    if (empty($stored)) {
        $stored = csrf_token();
    }

    // 1. Direct valid token match
    if (!empty($submitted) && hash_equals($stored, $submitted)) {
        return;
    }

    // 2. Same-origin fallback for authenticated admin sessions (prevents lockout on cached HTML forms)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($referer) && !empty($host) && isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
        $refHost = parse_url($referer, PHP_URL_HOST);
        $cleanHost = preg_replace('/:\d+$/', '', $host);
        if ($refHost === $cleanHost || $refHost === preg_replace('/^www\./i', '', $cleanHost) || $cleanHost === preg_replace('/^www\./i', '', $refHost)) {
            // Same origin verified for logged in admin — re-sync token safely
            $_SESSION['csrf_token'] = csrf_token();
            return;
        }
    }

    // 3. Genuine CSRF mismatch or unauthorized external post
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Forbidden - Security Verification</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh; font-family: sans-serif;">
        <div class="card border-0 shadow-lg rounded-4 p-4 text-center" style="max-width: 480px; width: 90%;">
            <div class="mb-3 text-warning">
                <i class="fas fa-shield-alt fa-3x"></i>
            </div>
            <h4 class="fw-bold mb-2">Session Verification</h4>
            <p class="text-muted small mb-4">A security session mismatch occurred (CSRF). This often happens if your session timed out or a form was cached. Please click below to reload safely.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-sync-alt me-2"></i> Reload Page
                </a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Generate a one-time form nonce to prevent browser POST resubmit (double-submit / hard refresh issue).
 * Each form gets a unique nonce stored in session.
 */
function form_nonce_input(string $formName = 'default'): string
{
    if (session_status() === PHP_SESSION_NONE) {
        include_once __DIR__ . '/../../includes/session_setup.php';
    }
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['form_nonces'][$formName] = $nonce;
    $escaped = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
    $escapedName = htmlspecialchars($formName, ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"_form_nonce\" value=\"{$escaped}\"><input type=\"hidden\" name=\"_form_name\" value=\"{$escapedName}\">";
}

/**
 * Verify and consume a one-time form nonce.
 * Returns true if valid. Returns false if nonce is missing/already used (double submit / hard refresh).
 * After validation, the nonce is DELETED from session so it cannot be reused.
 */
function verify_form_nonce(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        include_once __DIR__ . '/../../includes/session_setup.php';
    }
    $formName  = $_POST['_form_name'] ?? 'default';
    $submitted = $_POST['_form_nonce'] ?? '';
    $stored    = $_SESSION['form_nonces'][$formName] ?? null;

    // Consume the nonce immediately — cannot be reused ever
    unset($_SESSION['form_nonces'][$formName]);

    if (empty($stored) || empty($submitted)) return false;
    return hash_equals($stored, $submitted);
}
