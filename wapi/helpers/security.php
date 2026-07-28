<?php
/**
 * WAPI SaaS Platform - Security Helpers
 * XSS protection, input sanitization, and security utilities
 */

/**
 * Escape output for HTML
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize email
 */
function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize integer
 */
function sanitizeInt($value) {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize float / decimal number
 */
function sanitizeFloat($value) {
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Sanitize URL
 */
function sanitizeUrl($url) {
    return filter_var(trim($url), FILTER_SANITIZE_URL);
}

/**
 * Clean HTML content (allow basic tags)
 */
function cleanHtml($html) {
    return strip_tags($html, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><img><blockquote><code><pre>');
}

/**
 * Validate reCAPTCHA
 */
function verifyRecaptcha($response) {
    $secretKey = setting('recaptcha_secret_key');
    if (empty($secretKey)) return true; // Skip if not configured

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $response,
        'remoteip' => getUserIP()
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) return false;

    $json = json_decode($result, true);
    return $json['success'] ?? false;
}

/**
 * Encrypt data
 */
function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt data
 */
function decryptData($encryptedData) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $parts = explode('::', base64_decode($encryptedData), 2);
    if (count($parts) !== 2) return false;
    return openssl_decrypt($parts[1], 'AES-256-CBC', $key, 0, $parts[0]);
}

/**
 * Rate limiter (simple session-based)
 */
function rateLimit($key, $maxAttempts = 10, $windowSeconds = 60) {
    $cacheKey = 'rate_limit_' . $key;
    $now = time();

    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    if ($now > $_SESSION[$cacheKey]['reset_at']) {
        $_SESSION[$cacheKey] = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $_SESSION[$cacheKey]['count']++;

    return $_SESSION[$cacheKey]['count'] <= $maxAttempts;
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://checkout.razorpay.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com;");
}
