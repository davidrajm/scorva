<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

/**
 * Cookie + transient sessions for token-portal reviewers. Reviewers
 * authenticate with their emailed token + password — no WordPress login —
 * so their session lives outside WordPress auth entirely.
 */
final class ReviewerSessionService
{
    public const COOKIE_NAME = 'pr_reviewer_session';

    private const TRANSIENT_PREFIX = 'pr_rev_sess_';

    private const TTL_SECONDS = 604800; // 7 days

    /**
     * Create a session and set the cookie. Returns the session key.
     */
    public function start(int $reviewer_id, int $user_id, int $session_id): string
    {
        $key = bin2hex(random_bytes(32));
        $payload = [
            'reviewer_id' => $reviewer_id,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'created_at' => time(),
        ];

        if (function_exists('set_transient')) {
            set_transient(self::TRANSIENT_PREFIX . $key, $payload, self::TTL_SECONDS);
        }

        $this->set_cookie($key, time() + self::TTL_SECONDS);
        $_COOKIE[self::COOKIE_NAME] = $key;

        return $key;
    }

    /**
     * Validated context for the current request, or null when there is no
     * valid reviewer session.
     *
     * @return array{reviewer_id: int, user_id: int, session_id: int, created_at: int}|null
     */
    public function get_context(): ?array
    {
        $key = $this->cookie_key();
        if ($key === '' || !function_exists('get_transient')) {
            return null;
        }

        $payload = get_transient(self::TRANSIENT_PREFIX . $key);
        if (!is_array($payload)) {
            return null;
        }

        $reviewer_id = (int) ($payload['reviewer_id'] ?? 0);
        $session_id = (int) ($payload['session_id'] ?? 0);
        if ($reviewer_id <= 0 || $session_id <= 0) {
            return null;
        }

        return [
            'reviewer_id' => $reviewer_id,
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'session_id' => $session_id,
            'created_at' => (int) ($payload['created_at'] ?? 0),
        ];
    }

    public function destroy(): void
    {
        $key = $this->cookie_key();
        if ($key !== '' && function_exists('delete_transient')) {
            delete_transient(self::TRANSIENT_PREFIX . $key);
        }

        $this->set_cookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    private function cookie_key(): string
    {
        $key = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');

        return preg_match('/^[a-f0-9]{64}$/i', $key) === 1 ? $key : '';
    }

    private function set_cookie(string $value, int $expires): void
    {
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => function_exists('is_ssl') ? is_ssl() : true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
