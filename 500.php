<?php
/**
 * Custom 500 Internal Server Error Page
 */
http_response_code(500);
// Log the error
error_log('[500] Internal Server Error at ' . date('Y-m-d H:i:s') . ' URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Server Error — Sagar Starter's</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .error-box { text-align: center; padding: 60px 40px; max-width: 520px; }
        .error-code { font-size: 8rem; font-weight: 900; color: #dc3545; line-height: 1; }
        .error-title { font-size: 1.8rem; font-weight: 700; color: #222; margin: 16px 0 8px; }
        .error-desc { color: #666; margin-bottom: 32px; line-height: 1.6; }
        .btn-home { background: #007aff; color: #fff !important; padding: 12px 32px; border-radius: 30px; text-decoration: none !important; font-weight: 600; display: inline-block; }
        .btn-home:hover { background: #005ecc; color: #fff !important; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-code">500</div>
        <h1 class="error-title">Something Went Wrong</h1>
        <p class="error-desc">We're experiencing a temporary server issue. Our team has been notified and is working on a fix.<br>Please try again in a few minutes.</p>
        <a href="/" class="btn-home">
            <i class="fas fa-home me-2"></i>Go to Homepage
        </a>
    </div>
</body>
</html>
