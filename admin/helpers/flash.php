<?php
/**
 * Admin Helper: Flash Messages
 *
 * Provides simple session-based one-time messages.
 */

/**
 * Store a flash message in session.
 *
 * @param string $type    Bootstrap alert type: success, danger, warning, info
 * @param string $message Message text (may contain safe HTML)
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) include_once __DIR__ . '/../../includes/session_setup.php';
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 * Returns null if no flash message is set.
 *
 * @return array|null  ['type' => string, 'message' => string]
 */
function get_flash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) include_once __DIR__ . '/../../includes/session_setup.php';
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render the flash message as a Bootstrap alert div.
 * Outputs nothing if no flash is set.
 */
function render_flash(): void
{
    $flash = get_flash();
    if ($flash) {
        $type    = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = $flash['message']; // Allowed to have safe HTML (e.g. <b>)
        echo "<div class=\"alert alert-{$type} alert-dismissible py-2\" role=\"alert\">";
        echo "<i class=\"fas fa-info-circle me-2\"></i>{$message}";
        echo "<button type=\"button\" class=\"btn-close\" data-mdb-dismiss=\"alert\" aria-label=\"Close\"></button>";
        echo "</div>";
    }
}

function display_flash(): void
{
    render_flash();
}
