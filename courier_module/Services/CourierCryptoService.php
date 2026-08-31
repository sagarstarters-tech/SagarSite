<?php
declare(strict_types=1);

namespace CourierModule\Services;

/**
 * Class CourierCryptoService
 * Handles AES-256-CBC encryption and decryption of courier API tokens,
 * passwords, and client secrets so credentials are never stored in plaintext.
 */
class CourierCryptoService
{
    private static string $cipher = 'AES-256-CBC';

    /**
     * Get or derive secret encryption key
     */
    private static function getKey(): string
    {
        if (defined('SOCIAL_ENCRYPTION_KEY') && !empty(SOCIAL_ENCRYPTION_KEY)) {
            return hash('sha256', SOCIAL_ENCRYPTION_KEY, true);
        }
        if (!empty($_ENV['SOCIAL_ENCRYPTION_KEY'])) {
            return hash('sha256', $_ENV['SOCIAL_ENCRYPTION_KEY'], true);
        }
        // Fallback to deterministic server-side key
        $salt = defined('DB_NAME') ? DB_NAME : 'SagarSite_db';
        return hash('sha256', 'SagarStarters_CourierKey_' . $salt, true);
    }

    /**
     * Encrypt plaintext string
     */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        // If already encrypted prefix detected, return as-is
        if (strpos($plaintext, 'enc::') === 0) {
            return $plaintext;
        }

        $key = self::getKey();
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($plaintext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);

        return 'enc::' . base64_encode($iv . $hmac . $ciphertext_raw);
    }

    /**
     * Decrypt ciphertext string
     */
    public static function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        // If not encrypted with our prefix, return as plain (handles legacy/unencrypted migration)
        if (strpos($ciphertext, 'enc::') !== 0) {
            return $ciphertext;
        }

        $raw = base64_decode(substr($ciphertext, 5));
        $key = self::getKey();
        $ivlen = openssl_cipher_iv_length(self::$cipher);

        $iv = substr($raw, 0, $ivlen);
        $hmac = substr($raw, $ivlen, 32);
        $ciphertext_raw = substr($raw, $ivlen + 32);

        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
        if (!hash_equals($hmac, $calcmac)) {
            return null; // Authentication failed / tampered
        }

        $decrypted = openssl_decrypt($ciphertext_raw, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        return ($decrypted !== false) ? $decrypted : null;
    }

    /**
     * Mask token for safe admin UI display
     */
    public static function mask(?string $plaintext, int $visibleChars = 4): string
    {
        if (empty($plaintext)) {
            return '';
        }
        $decrypted = self::decrypt($plaintext);
        if (empty($decrypted)) {
            return '';
        }
        $len = strlen($decrypted);
        if ($len <= $visibleChars) {
            return str_repeat('•', $len);
        }
        return substr($decrypted, 0, $visibleChars) . str_repeat('•', max(8, $len - ($visibleChars * 2))) . substr($decrypted, -$visibleChars);
    }
}
