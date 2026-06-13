<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

/**
 * AES-256-CBC encryption for secrets at rest, keyed from the WordPress auth
 * salt. Encrypted values carry a marker prefix so encryption is idempotent —
 * register_setting sanitize callbacks run twice when an option is first
 * created, and a second pass must not re-encrypt.
 */
final class Crypto
{
    public const ENC_PREFIX = '$pr-enc$';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '' || str_starts_with($plaintext, self::ENC_PREFIX)) {
            return $plaintext;
        }

        $key = self::encryption_key();
        if (!function_exists('openssl_encrypt') || $key === '') {
            return self::ENC_PREFIX . base64_encode($plaintext);
        }

        $iv = random_bytes(16);
        $derived = substr(hash('sha256', $key, true), 0, 32);
        $encoded = openssl_encrypt($plaintext, 'AES-256-CBC', $derived, 0, $iv);

        return self::ENC_PREFIX . base64_encode($iv . $encoded);
    }

    public static function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (str_starts_with($encrypted, self::ENC_PREFIX)) {
            $encrypted = substr($encrypted, strlen(self::ENC_PREFIX));
        }

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            return '';
        }

        $key = self::encryption_key();
        if (!function_exists('openssl_decrypt') || $key === '') {
            return $decoded;
        }

        if (strlen($decoded) < 16) {
            return '';
        }

        $iv = substr($decoded, 0, 16);
        $payload = substr($decoded, 16);
        $derived = substr(hash('sha256', $key, true), 0, 32);
        $plain = openssl_decrypt($payload, 'AES-256-CBC', $derived, 0, $iv);

        return is_string($plain) ? $plain : '';
    }

    private static function encryption_key(): string
    {
        if (function_exists('wp_salt')) {
            return (string) wp_salt('auth');
        }
        if (defined('AUTH_KEY')) {
            return (string) AUTH_KEY;
        }

        return '';
    }
}
