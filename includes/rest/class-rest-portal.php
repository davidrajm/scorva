<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\ReviewerSessionService;
use ProjectReviews\Services\TokenService;

/**
 * Token-portal authentication for reviewers. These endpoints are public by
 * design: reviewers have no WordPress account and authenticate with the
 * token + password from their invitation email.
 */
final class Rest_Portal
{
    private const THROTTLE_PREFIX = 'pr_portal_fail_';

    private const THROTTLE_LIMIT = 10;

    private const THROTTLE_WINDOW = 600;

    public static function register_routes(): void
    {
        $public = static fn (): bool => true;

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/portal/token-status',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'token_status'],
                'permission_callback' => $public,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/portal/auth',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'auth'],
                'permission_callback' => $public,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/portal/session',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'session'],
                'permission_callback' => $public,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/portal/logout',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'logout'],
                'permission_callback' => $public,
            ]
        );
    }

    /**
     * @return array{valid: bool}
     */
    public static function token_status(\WP_REST_Request $request): array
    {
        $token = self::sanitize_token((string) ($request->get_param('token') ?? ''));
        if ($token === '') {
            return ['valid' => false];
        }

        return ['valid' => (new PanelRepository())->find_reviewer_by_token($token) !== null];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function auth(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $token = self::sanitize_token(
            (string) ($body['token'] ?? $request->get_param('token') ?? '')
        );
        $password = (string) ($body['password'] ?? '');

        if ($token === '') {
            return self::invalid_token_error();
        }

        $throttled = self::check_throttle($token);
        if ($throttled instanceof \WP_Error) {
            return $throttled;
        }

        $reviewer = (new PanelRepository())->find_reviewer_by_token($token);
        if ($reviewer === null) {
            return self::invalid_token_error();
        }

        $tokens = new TokenService();
        if (!$tokens->verify_password($password, (string) ($reviewer['password_hash'] ?? ''))) {
            self::record_failure($token);

            return new \WP_Error(
                'pr_portal_invalid_password',
                __('Incorrect password. Use the password from your most recent invitation email.', 'project-reviews'),
                ['status' => 401]
            );
        }

        $resolved = self::resolve_session_for_reviewer($reviewer);
        if ($resolved instanceof \WP_Error) {
            return $resolved;
        }
        [$session_id, $session] = $resolved;

        self::clear_throttle($token);

        (new ReviewerSessionService())->start(
            (int) $reviewer['id'],
            (int) ($reviewer['user_id'] ?? 0),
            $session_id
        );

        return self::context_payload($reviewer, $session_id, $session);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function session(\WP_REST_Request $request): array|\WP_Error
    {
        unset($request);

        $context = (new ReviewerSessionService())->get_context();
        if ($context === null) {
            return new \WP_Error(
                'pr_portal_unauthorized',
                __('Your session has expired. Open your review link and enter your password again.', 'project-reviews'),
                ['status' => 401]
            );
        }

        $reviewer = (new PanelRepository())->find_reviewer($context['reviewer_id']);
        if ($reviewer === null) {
            (new ReviewerSessionService())->destroy();

            return self::invalid_token_error();
        }

        $session = (new SessionRepository())->find_by_id($context['session_id']);

        return self::context_payload($reviewer, $context['session_id'], $session);
    }

    /**
     * @return array{logged_out: true}
     */
    public static function logout(\WP_REST_Request $request): array
    {
        unset($request);

        (new ReviewerSessionService())->destroy();

        return ['logged_out' => true];
    }

    /**
     * Resolve and validate the project this reviewer belongs to.
     *
     * @param array<string, mixed> $reviewer
     * @return array{0: int, 1: array<string, mixed>|null}|\WP_Error
     */
    private static function resolve_session_for_reviewer(array $reviewer): array|\WP_Error
    {
        $panel = (new PanelRepository())->find_by_id((int) ($reviewer['panel_id'] ?? 0));
        if ($panel === null) {
            return self::invalid_token_error();
        }

        $session_id = (int) ($panel['session_id'] ?? 0);
        $session = (new SessionRepository())->find_by_id($session_id);
        if ($session === null) {
            return self::invalid_token_error();
        }

        if ((string) ($session['status'] ?? '') === 'closed') {
            return new \WP_Error(
                'pr_portal_session_closed',
                sprintf(
                    /* translators: %s: application display name */
                    __('This review project has been closed. Contact the coordinator if you believe this is a mistake. — %s', 'project-reviews'),
                    PluginSettings::app_display_name()
                ),
                ['status' => 403]
            );
        }

        return [$session_id, $session];
    }

    /**
     * @param array<string, mixed> $reviewer
     * @param array<string, mixed>|null $session
     * @return array<string, mixed>
     */
    private static function context_payload(array $reviewer, int $session_id, ?array $session): array
    {
        return [
            'authenticated' => true,
            'reviewer' => [
                'id' => (int) ($reviewer['id'] ?? 0),
                'name' => (string) ($reviewer['name'] ?? ''),
                'email' => (string) ($reviewer['email'] ?? ''),
                'user_id' => (int) ($reviewer['user_id'] ?? 0),
            ],
            'project' => [
                'id' => $session_id,
                'title' => is_array($session) ? (string) ($session['title'] ?? '') : '',
            ],
        ];
    }

    private static function sanitize_token(string $token): string
    {
        $token = trim($token);

        return preg_match(TokenService::TOKEN_PATTERN, $token) === 1 ? $token : '';
    }

    private static function invalid_token_error(): \WP_Error
    {
        return new \WP_Error(
            'pr_portal_invalid_token',
            __('This review link is no longer valid. Contact the coordinator for a new invitation.', 'project-reviews'),
            ['status' => 401]
        );
    }

    private static function check_throttle(string $token): bool|\WP_Error
    {
        if (!function_exists('get_transient')) {
            return true;
        }

        $failures = (int) get_transient(self::THROTTLE_PREFIX . md5($token));
        if ($failures >= self::THROTTLE_LIMIT) {
            return new \WP_Error(
                'pr_portal_throttled',
                __('Too many failed attempts. Try again later.', 'project-reviews'),
                ['status' => 429]
            );
        }

        return true;
    }

    private static function record_failure(string $token): void
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return;
        }

        $key = self::THROTTLE_PREFIX . md5($token);
        $failures = (int) get_transient($key);
        set_transient($key, $failures + 1, self::THROTTLE_WINDOW);
    }

    private static function clear_throttle(string $token): void
    {
        if (function_exists('delete_transient')) {
            delete_transient(self::THROTTLE_PREFIX . md5($token));
        }
    }
}
