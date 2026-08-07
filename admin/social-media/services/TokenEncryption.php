<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

/**
 * Class TokenEncryption
 * Handles encryption and decryption of social media tokens.
 */
class TokenEncryption {
    
    private const CIPHER_METHOD = 'aes-256-gcm';

    /**
     * Gets the encryption key from environment variables.
     *
     * @return string
     * @throws \Exception
     */
    private static function getKey(): string {
        $key = function_exists('_env') ? _env('SOCIAL_ENCRYPTION_KEY') : null;
        if (!$key) {
            // For fallback or when env is not fully initialized. In production, this should throw an error.
            throw new \Exception("SOCIAL_ENCRYPTION_KEY is not defined in environment.");
        }
        return hex2bin($key) ?: $key; // Expecting hex string, fallback to raw
    }

    /**
     * Encrypts plaintext string.
     *
     * @param string $plaintext
     * @return string
     */
    public static function encrypt(string $plaintext): string {
        try {
            $key = self::getKey();
            $ivLen = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = openssl_random_pseudo_bytes($ivLen);
            $tag = "";
            $ciphertext = openssl_encrypt($plaintext, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
            
            return base64_encode($iv . $tag . $ciphertext);
        } catch (\Exception $e) {
            error_log("Encryption Error: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Decrypts ciphertext string.
     *
     * @param string $ciphertext
     * @return string
     */
    public static function decrypt(string $ciphertext): string {
        try {
            $key = self::getKey();
            $data = base64_decode($ciphertext);
            $ivLen = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = substr($data, 0, $ivLen);
            $tag = substr($data, $ivLen, 16);
            $cipher = substr($data, $ivLen + 16);
            
            return openssl_decrypt($cipher, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
        } catch (\Exception $e) {
            error_log("Decryption Error: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Generates a random 32-byte key.
     *
     * @return string
     */
    public static function generateKey(): string {
        return bin2hex(random_bytes(32));
    }
}
