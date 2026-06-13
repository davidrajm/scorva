<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\ReviewerSessionService;

final class Rest_Auth
{
    /**
     * Permission callback for reviewer-portal endpoints: requires a valid
     * token-portal session cookie (no WordPress login, no REST nonce).
     */
    public static function require_reviewer_session(): callable
    {
        return static function (): bool|\WP_Error {
            if (self::reviewer_session_context() !== null) {
                return true;
            }

            return new \WP_Error(
                'pr_portal_unauthorized',
                __('Your session has expired. Open your review link and enter your password again.', 'scorva'),
                ['status' => 401]
            );
        };
    }

    /**
     * Validated portal-session context for the current request, or null.
     *
     * @return array{reviewer_id: int, user_id: int, session_id: int, created_at: int}|null
     */
    public static function reviewer_session_context(): ?array
    {
        return (new ReviewerSessionService())->get_context();
    }

    /**
     * Wrap a permission callback so a valid token-portal session also passes.
     * Portal reviewers have no WordPress login, capabilities, or REST nonce.
     *
     * @param callable(\WP_REST_Request=): (bool|\WP_Error) $permission
     */
    public static function allow_reviewer_session(callable $permission): callable
    {
        return static function (\WP_REST_Request $request) use ($permission): bool|\WP_Error {
            if (self::reviewer_session_context() !== null) {
                return true;
            }

            return $permission($request);
        };
    }

    /**
     * Acting reviewer identity for marks and assignments: the portal
     * session's roster identity when present, otherwise the WordPress user.
     */
    public static function current_actor_id(): int
    {
        $context = self::reviewer_session_context();
        if ($context !== null && $context['user_id'] > 0) {
            return $context['user_id'];
        }

        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }

    public static function require_logged_in(): callable
    {
        return static function (): bool|\WP_Error {
            if (!is_user_logged_in()) {
                return new \WP_Error(
                    'rest_not_logged_in',
                    __('You must be logged in to access this endpoint.', 'scorva'),
                    ['status' => 401]
                );
            }

            return true;
        };
    }

    public static function require_cap(string $cap): callable
    {
        return static function () use ($cap): bool|\WP_Error {
            return self::check_caps([$cap]);
        };
    }

    /**
     * @param list<string> $caps
     */
    public static function require_any_cap(array $caps): callable
    {
        return static function () use ($caps): bool|\WP_Error {
            return self::check_caps($caps, true);
        };
    }

    /**
     * @param list<string> $caps
     */
    private static function check_caps(array $caps, bool $any = false): bool|\WP_Error
    {
        $logged_in = self::require_logged_in()();
        if ($logged_in instanceof \WP_Error) {
            return $logged_in;
        }

        if ($any) {
            foreach ($caps as $cap) {
                if (current_user_can($cap)) {
                    return true;
                }
            }
        } elseif ($caps !== [] && current_user_can($caps[0])) {
            return true;
        }

        return new \WP_Error(
            'rest_forbidden',
            __('You do not have permission to access this endpoint.', 'scorva'),
            ['status' => 403]
        );
    }

    public static function require_any_pr_cap(): callable
    {
        return static function (): bool|\WP_Error {
            $logged_in = self::require_logged_in()();
            if ($logged_in instanceof \WP_Error) {
                return $logged_in;
            }

            foreach (Capabilities::all() as $cap) {
                if (current_user_can($cap)) {
                    return true;
                }
            }

            return new \WP_Error(
                'rest_forbidden',
                sprintf(
                    /* translators: %s: application display name */
                    __('You do not have permission to access %s.', 'scorva'),
                    PluginSettings::app_display_name()
                ),
                ['status' => 403]
            );
        };
    }

    /**
     * @param callable(): bool|\WP_Error $permission
     */
    public static function with_rest_nonce(callable $permission): callable
    {
        return static function (\WP_REST_Request $request) use ($permission): bool|\WP_Error {
            $nonce_check = self::verify_rest_nonce($request);
            if ($nonce_check instanceof \WP_Error) {
                return $nonce_check;
            }

            return $permission();
        };
    }

    public static function verify_rest_nonce(\WP_REST_Request $request): bool|\WP_Error
    {
        $logged_in = self::require_logged_in()();
        if ($logged_in instanceof \WP_Error) {
            return $logged_in;
        }

        $nonce = (string) ($request->get_header('X-WP-Nonce') ?? '');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_cookie_invalid_nonce',
                __('Invalid or missing REST nonce.', 'scorva'),
                ['status' => 403]
            );
        }

        return true;
    }
}
