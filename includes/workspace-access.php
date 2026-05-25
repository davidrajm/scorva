<?php

declare(strict_types=1);

namespace ProjectReviews;

final class WorkspaceAccess
{
    public static function register_hooks(): void
    {
        add_filter('login_redirect', [self::class, 'filter_login_redirect'], 10, 3);
        add_action('admin_init', [self::class, 'block_reviewer_only_admin'], 1);
        add_filter('show_admin_bar', [self::class, 'filter_show_admin_bar']);
    }

    /**
     * @param \WP_User|\WP_Error $user
     */
    public static function filter_login_redirect(
        string $redirect_to,
        string $requested_redirect_to,
        $user
    ): string {
        if ($user instanceof \WP_Error || !isset($user->ID) || (int) $user->ID <= 0) {
            return $redirect_to;
        }

        $has_coordinator = Capabilities::user_has_coordinator_workspace_access_for_user($user);
        $reviewer_only = Capabilities::user_is_reviewer_only($user);

        if (!$has_coordinator && !$reviewer_only) {
            return $redirect_to;
        }

        $landing_home = home_url('/reviews/');

        if (self::is_plugin_app_url($requested_redirect_to)) {
            return $requested_redirect_to;
        }

        if (self::is_plugin_app_url($redirect_to)) {
            return $redirect_to;
        }

        if ($redirect_to === '' || self::is_wp_admin_url($redirect_to) || self::is_wp_admin_url($requested_redirect_to)) {
            return $landing_home;
        }

        return $redirect_to;
    }

    public static function block_reviewer_only_admin(): void
    {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }

        if (!Capabilities::user_is_reviewer_only()) {
            return;
        }

        $home = Capabilities::workspace_home_url_for_user();
        if ($home === null) {
            return;
        }

        wp_safe_redirect($home);
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $GLOBALS['pr_test_exit_called'] = true;

            return;
        }

        exit;
    }

    /**
     * @param bool|string $show
     * @return bool|string
     */
    public static function filter_show_admin_bar($show)
    {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return $show;
        }

        if (Capabilities::user_is_reviewer_only()) {
            return false;
        }

        return $show;
    }

    private static function is_wp_admin_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $admin = function_exists('admin_url') ? admin_url() : home_url('/wp-admin/');

        return str_starts_with($url, $admin) || str_contains($url, '/wp-admin');
    }

    private static function is_plugin_app_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $mark_home = home_url('/reviews/mark/');
        $coordinator_home = home_url('/reviews/');

        return str_starts_with($url, $mark_home)
            || str_starts_with($url, $coordinator_home);
    }
}
