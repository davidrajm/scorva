<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\PluginSettings;

final class Rest_Auth
{
    public static function require_logged_in(): callable
    {
        return static function (): bool|\WP_Error {
            if (!is_user_logged_in()) {
                return new \WP_Error(
                    'rest_not_logged_in',
                    __('You must be logged in to access this endpoint.', 'project-reviews'),
                    ['status' => 401]
                );
            }

            $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
            if ($user_id > 0 && function_exists('get_user_meta')) {
                $disabled = get_user_meta($user_id, 'pr_account_disabled', true);
                if ($disabled === '1' || $disabled === 'yes' || $disabled === true) {
                    return new \WP_Error(
                        'pr_account_disabled',
                        sprintf(
                            /* translators: %s: application display name */
                            __('This account has been disabled for %s.', 'project-reviews'),
                            PluginSettings::app_display_name()
                        ),
                        ['status' => 403]
                    );
                }
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
            __('You do not have permission to access this endpoint.', 'project-reviews'),
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
                    __('You do not have permission to access %s.', 'project-reviews'),
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
                __('Invalid or missing REST nonce.', 'project-reviews'),
                ['status' => 403]
            );
        }

        return true;
    }
}
