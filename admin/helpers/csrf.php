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
    $submitted = $_POST['_csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (empty($stored) || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Security session mismatch (CSRF). This often happens if your session timed out or you have multiple tabs open. Please refresh the page and try again.</p>');
    }
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
