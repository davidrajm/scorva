<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

/**
 * Credentials for token-based reviewer portal access: a unique URL token
 * identifies the reviewer, a bcrypt-hashed password authenticates them.
 * The password is additionally stored AES-encrypted so coordinators can
 * resend the original credentials email.
 */
final class TokenService
{
    public const TOKEN_PATTERN = '/^[a-f0-9]{64}$/i';

    private const PASSWORD_LENGTH = 12;

    public function generate_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function is_valid_token_format(string $token): bool
    {
        return preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    public function generate_password(): string
    {
        if (function_exists('wp_generate_password')) {
            return (string) wp_generate_password(self::PASSWORD_LENGTH, true, false);
        }

        $raw = str_replace(['/', '+', '='], '', base64_encode(random_bytes(self::PASSWORD_LENGTH)));

        return substr($raw, 0, self::PASSWORD_LENGTH);
    }

    public function hash_password(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function verify_password(string $plain, string $hash): bool
    {
        if ($plain === '' || $hash === '') {
            return false;
        }

        return password_verify($plain, $hash);
    }

    public function encrypt_password(string $plain): string
    {
        return Crypto::encrypt($plain);
    }

    public function decrypt_password(string $encrypted): string
    {
        return Crypto::decrypt($encrypted);
    }
}
