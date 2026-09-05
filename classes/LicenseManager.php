<?php
/**
 * ============================================================
 *  LICENSE MANAGER — Single-Domain License Lock System
 * ============================================================
 *  Guards software against unauthorized domain redistribution.
 *  - Automatically bypasses on localhost / development
 *  - Enforces domain-matching cryptographic signature on live domains
 * ============================================================
 */

class LicenseManager
{
    // Master secret key for HMAC signature verification
    private static $secret = 'SAGAR_ECOMMERCE_SECURE_SALT_v1_2026';

    /**
     * Normalize domain name (lowercase, strip protocol, www, port, paths)
     */
    public static function cleanDomain($domain)
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];
        return strtolower(trim($domain));
    }

    /**
     * Check if the current environment should bypass licensing (Localhost / Development)
     */
    public static function isBypassed()
    {
        // CLI or background cron scripts bypass
        if (php_sapi_name() === 'cli') {
            return true;
        }

        // Development mode bypass
        if (defined('APP_ENV') && APP_ENV === 'development') {
            return true;
        }

        $host = self::cleanDomain($_SERVER['HTTP_HOST'] ?? '');

        // Empty, localhost, IP addresses or local development domains bypass
        if (empty($host) || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        if (substr($host, -6) === '.local' || substr($host, -5) === '.test') {
            return true;
        }

        return false;
    }

    /**
     * Generate a signed License Key for a specific domain
     */
    public static function generateKey($domain, $clientName = 'Customer', $expiry = null)
    {
        $cleanDomain = self::cleanDomain($domain);
        if (empty($cleanDomain)) {
            return false;
        }

        $payloadData = [
            'd' => $cleanDomain,
            'c' => trim($clientName),
            't' => date('Y-m-d'),
            'e' => $expiry // optional expiration date Y-m-d or null
        ];

        $json = json_encode($payloadData);
        $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = strtoupper(substr(hash_hmac('sha256', $b64, self::$secret), 0, 16));

        return 'LIC-' . $b64 . '.' . $sig;
    }

    /**
     * Decode and verify a license key against a domain
     */
    public static function verifyKey($licenseKey, $targetDomain = null)
    {
        if (empty($licenseKey) || !is_string($licenseKey)) {
            return ['valid' => false, 'reason' => 'License key is missing or empty.'];
        }

        $licenseKey = trim($licenseKey);
        if (strpos($licenseKey, 'LIC-') !== 0) {
            return ['valid' => false, 'reason' => 'Invalid license key format.'];
        }

        $raw = substr($licenseKey, 4);
        $parts = explode('.', $raw);
        if (count($parts) !== 2) {
            return ['valid' => false, 'reason' => 'Corrupted license signature structure.'];
        }

        list($b64, $sig) = $parts;

        // Verify cryptographic signature
        $expectedSig = strtoupper(substr(hash_hmac('sha256', $b64, self::$secret), 0, 16));
        if (!hash_equals($expectedSig, strtoupper($sig))) {
            return ['valid' => false, 'reason' => 'Invalid cryptographic license signature. Key has been altered.'];
        }

        // Decode payload
        $json = base64_decode(strtr($b64, '-_', '+/'));
        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['d'])) {
            return ['valid' => false, 'reason' => 'Invalid license payload.'];
        }

        // Verify expiration if present
        if (!empty($data['e'])) {
            if (strtotime($data['e']) < time()) {
                return ['valid' => false, 'reason' => 'This software license expired on ' . htmlspecialchars($data['e']) . '.'];
            }
        }

        // Verify domain match
        if ($targetDomain === null) {
            $targetDomain = $_SERVER['HTTP_HOST'] ?? '';
        }

        $currentClean = self::cleanDomain($targetDomain);
        $licensedDomain = self::cleanDomain($data['d']);

        if ($currentClean !== $licensedDomain) {
            return [
                'valid' => false,
                'reason' => "Domain mismatch! This license is issued exclusively to '{$licensedDomain}', but current domain is '{$currentClean}'.",
                'licensed_domain' => $licensedDomain,
                'current_domain' => $currentClean
            ];
        }

        return [
            'valid' => true,
            'details' => $data
        ];
    }

    /**
     * Enforce licensing globally. Call during bootstrap.
     */
    public static function enforce()
    {
        // Always bypass localhost/development
        if (self::isBypassed()) {
            return true;
        }

        // Fetch License Key from environment or database settings
        $licenseKey = '';
        if (function_exists('_env')) {
            $licenseKey = _env('LICENSE_KEY', '');
        }

        if (empty($licenseKey) && isset($_ENV['LICENSE_KEY'])) {
            $licenseKey = $_ENV['LICENSE_KEY'];
        }

        // Also check getenv
        if (empty($licenseKey)) {
            $licenseKey = getenv('LICENSE_KEY') ?: '';
        }

        $currentHost = self::cleanDomain($_SERVER['HTTP_HOST'] ?? '');
        $verification = self::verifyKey($licenseKey, $currentHost);

        if (!$verification['valid']) {
            self::renderLockScreen($verification['reason'], $currentHost);
            exit;
        }

        return true;
    }

    /**
     * Render a locked screen if license is invalid on production
     */
    private static function renderLockScreen($reason, $currentHost)
    {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Software License Verification Required</title>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                    color: #f8fafc;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .card {
                    background: rgba(30, 41, 59, 0.85);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                    max-width: 540px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }
                .icon-box {
                    width: 72px;
                    height: 72px;
                    background: rgba(239, 68, 68, 0.15);
                    border: 2px solid rgba(239, 68, 68, 0.4);
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 24px;
                    color: #ef4444;
                    font-size: 32px;
                }
                h1 {
                    font-size: 24px;
                    font-weight: 700;
                    margin-bottom: 12px;
                    color: #ffffff;
                }
                p.desc {
                    font-size: 14px;
                    line-height: 1.6;
                    color: #94a3b8;
                    margin-bottom: 24px;
                }
                .error-box {
                    background: rgba(15, 23, 42, 0.6);
                    border-left: 4px solid #ef4444;
                    padding: 14px 18px;
                    border-radius: 8px;
                    text-align: left;
                    font-size: 13px;
                    color: #fca5a5;
                    margin-bottom: 28px;
                    word-break: break-word;
                }
                .meta-badge {
                    display: inline-block;
                    padding: 6px 14px;
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 50px;
                    font-size: 12px;
                    color: #cbd5e1;
                    margin-bottom: 24px;
                }
                .notice {
                    font-size: 13px;
                    color: #64748b;
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                    padding-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon-box">🔒</div>
                <h1>Software License Required</h1>
                <p class="desc">This e-commerce application is protected by a single-domain software license. The system has detected an unverified or unauthorized domain registration.</p>
                
                <div class="meta-badge">Domain: <strong><?php echo htmlspecialchars($currentHost); ?></strong></div>
                
                <div class="error-box">
                    <strong>Error:</strong> <?php echo htmlspecialchars($reason); ?>
                </div>

                <p class="notice">If you are the website owner, please add your valid <code>LICENSE_KEY</code> to your <code>.env</code> file, or contact your software provider to activate your domain license.</p>
            </div>
        </body>
        </html>
        <?php
    }
}
