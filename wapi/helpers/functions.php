<?php
/**
 * WAPI SaaS Platform - Helper Functions
 * Common utility functions used throughout the application
 */

/**
 * Redirect to URL
 */
function redirect($path) {
    $url = (strpos($path, 'http') === 0) ? $path : baseUrl($path);
    header("Location: {$url}");
    exit;
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    // Strip redundant /wapi/ from the start of the path string
    $path = ltrim($path, '/');
    if (strpos($path, 'wapi/') === 0) {
        $path = substr($path, 5);
    }
    
    $url = rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    
    // Final deduplication (e.g., /wapi/wapi/ -> /wapi/)
    // Replaces multiple occurrences of /wapi with a single one
    return preg_replace('/(\/wapi)+/', '/wapi', $url);
}

/**
 * Get asset URL with cache busting
 */
function asset($path) {
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = file_exists($file) ? filemtime($file) : APP_VERSION;
    return baseUrl($path) . '?v=' . $version;
}

/**
 * Get site setting
 */
function setting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = new Settings();
    }
    return $settings->get($key, $default);
}

/**
 * Format date
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format date relative (e.g., "2 hours ago")
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Format number (e.g., 1234 -> 1.2K)
 */
function formatNumber($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return number_format($num);
}

/**
 * Format currency
 */
function formatCurrency($amount, $symbol = '₹') {
    return $symbol . number_format($amount, 2);
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate API key
 */
function generateApiKey() {
    return 'wapi_' . bin2hex(random_bytes(24));
}

/**
 * Generate API secret
 */
function generateApiSecret() {
    return bin2hex(random_bytes(32));
}

/**
 * Generate UUID v4
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Slugify text
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

/**
 * JSON response helper for AJAX
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Flash message system
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get user IP address
 */
function getUserIP() {
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Upload file handler
 */
function uploadFile($file, $directory = 'general', $allowedTypes = null) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = $allowedTypes ?? ALLOWED_EXTENSIONS;
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }

    $uploadDir = UPLOAD_DIR . $directory . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = time() . '_' . generateRandomString(8) . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => 'uploads/' . $directory . '/' . $filename,
            'full_path' => $filepath
        ];
    }

    return ['success' => false, 'message' => 'Failed to upload file.'];
}

/**
 * Pagination helper
 */
function paginate($totalItems, $currentPage = 1, $perPage = null) {
    $perPage = $perPage ?? ITEMS_PER_PAGE;
    $totalPages = max(1, ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $totalItems,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $urlPattern = '?page=%d') {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';

    // Previous
    $html .= '<li class="page-item ' . (!$pagination['has_prev'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . sprintf($urlPattern, $pagination['current_page'] - 1) . '"><i class="bi bi-chevron-left"></i></a></li>';

    // Pages
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, 1) . '">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . sprintf($urlPattern, $i) . '">' . $i . '</a></li>';
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $pagination['total_pages']) . '">' . $pagination['total_pages'] . '</a></li>';
    }

    // Next
    $html .= '<li class="page-item ' . (!$pagination['has_next'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . sprintf($urlPattern, $pagination['current_page'] + 1) . '"><i class="bi bi-chevron-right"></i></a></li>';

    $html .= '</ul></nav>';
    return $html;
}
