<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\PluginSettings;

if (!defined('PR_CAP_MANAGE_SESSIONS')) {
    define('PR_CAP_MANAGE_SESSIONS', 'pr_manage_sessions');
    define('PR_CAP_UPLOAD_STUDENTS', 'pr_upload_students');
    define('PR_CAP_MANAGE_PANELS', 'pr_manage_panels');
    define('PR_CAP_ASSIGN_REVIEWERS', 'pr_assign_reviewers');
    define('PR_CAP_CONFIGURE_WEIGHTS', 'pr_configure_weights');
    define('PR_CAP_CONFIRM_RUBRICS', 'pr_confirm_rubrics');
    define('PR_CAP_ENTER_MARKS', 'pr_enter_marks');
    define('PR_CAP_OVERRIDE_MARKS', 'pr_override_marks');
    define('PR_CAP_VIEW_REPORTS', 'pr_view_reports');
    define('PR_CAP_CLOSE_SESSION', 'pr_close_session');
    define('PR_CAP_MANAGE_SETTINGS', 'pr_manage_settings');
}

final class Capabilities
{
    public const ROLE_COORDINATOR = 'project_reviews_coordinator';
    public const ROLE_REVIEWER = 'project_reviews_reviewer';

    public static function all(): array
    {
        return [
            PR_CAP_MANAGE_SESSIONS,
            PR_CAP_UPLOAD_STUDENTS,
            PR_CAP_MANAGE_PANELS,
            PR_CAP_ASSIGN_REVIEWERS,
            PR_CAP_CONFIGURE_WEIGHTS,
            PR_CAP_CONFIRM_RUBRICS,
            PR_CAP_ENTER_MARKS,
            PR_CAP_OVERRIDE_MARKS,
            PR_CAP_VIEW_REPORTS,
            PR_CAP_CLOSE_SESSION,
            PR_CAP_MANAGE_SETTINGS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function coordinator_caps(): array
    {
        return array_values(
            array_filter(
                self::all(),
                static fn (string $cap): bool => $cap !== PR_CAP_ENTER_MARKS
            )
        );
    }

    public static function apply_defaults(): void
    {
        if (!function_exists('get_option')) {
            return;
        }

        self::refresh_role_display_names();

        $installed_version = (string) get_option('pr_caps_version', '0');
        if (defined('PR_PLUGIN_VERSION') && version_compare($installed_version, PR_PLUGIN_VERSION, '>=')) {
            return;
        }

        $administrator = get_role('administrator');
        if ($administrator !== null) {
            foreach (self::all() as $cap) {
                $administrator->add_cap($cap);
            }
        }

        $coordinator = get_role(self::ROLE_COORDINATOR);
        if ($coordinator !== null) {
            foreach (self::coordinator_caps() as $cap) {
                $coordinator->add_cap($cap);
            }
        }

        $reviewer = get_role(self::ROLE_REVIEWER);
        if ($reviewer !== null) {
            foreach (self::all() as $cap) {
                $reviewer->remove_cap($cap);
            }
            $reviewer->add_cap(PR_CAP_ENTER_MARKS);
        }

        if (function_exists('update_option') && defined('PR_PLUGIN_VERSION')) {
            update_option('pr_caps_version', PR_PLUGIN_VERSION);
        }
    }

    public static function user_has_coordinator_workspace_access(): bool
    {
        if (!function_exists('wp_get_current_user')) {
            return false;
        }

        return self::user_has_coordinator_workspace_access_for_user(wp_get_current_user());
    }

    /**
     * @param object{ID?: int}|int|null $user
     */
    public static function user_has_coordinator_workspace_access_for_user($user): bool
    {
        if (!function_exists('user_can')) {
            return false;
        }

        if (user_can($user, PR_CAP_OVERRIDE_MARKS) || user_can($user, PR_CAP_MANAGE_SETTINGS)) {
            return true;
        }

        foreach (self::coordinator_caps() as $cap) {
            if (user_can($user, $cap)) {
                return true;
            }
        }

        return false;
    }

    public static function user_has_reviewer_workspace_access(): bool
    {
        if (!function_exists('wp_get_current_user')) {
            return false;
        }

        return self::user_has_reviewer_workspace_access_for_user(wp_get_current_user());
    }

    /**
     * @param object{ID?: int}|int|null $user
     */
    public static function user_has_reviewer_workspace_access_for_user($user): bool
    {
        return function_exists('user_can') && user_can($user, PR_CAP_ENTER_MARKS);
    }

    /**
     * @param object{ID?: int}|int|null $user
     */
    public static function workspace_home_url_for_user($user = null): ?string
    {
        if ($user === null && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
        }

        if (self::user_has_coordinator_workspace_access_for_user($user)) {
            return home_url('/reviews/');
        }

        if (self::user_has_reviewer_workspace_access_for_user($user)) {
            return home_url('/reviews/mark/');
        }

        return null;
    }

    public static function user_is_reviewer_only($user = null): bool
    {
        if ($user === null && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
        }

        return self::user_has_reviewer_workspace_access_for_user($user)
            && !self::user_has_coordinator_workspace_access_for_user($user);
    }

    /**
     * Remove all plugin capabilities from every registered role (inverse of apply_defaults grants).
     */
    public static function remove_from_all_roles(): void
    {
        if (!function_exists('wp_roles')) {
            return;
        }

        $roles = wp_roles();
        if (!isset($roles->roles) || !is_array($roles->roles)) {
            return;
        }

        foreach (array_keys($roles->roles) as $role_id) {
            $role = get_role((string) $role_id);
            if ($role === null) {
                continue;
            }

            foreach (self::all() as $cap) {
                $role->remove_cap($cap);
            }
        }
    }

    /**
     * Remove custom plugin roles only when no users are assigned to them.
     */
    public static function remove_custom_roles_if_empty(): void
    {
        if (!function_exists('count_users') || !function_exists('remove_role')) {
            return;
        }

        foreach ([self::ROLE_COORDINATOR, self::ROLE_REVIEWER] as $role_id) {
            $counts = count_users(['role' => $role_id]);
            $total = 0;
            if (is_array($counts)) {
                $total = (int) ($counts['total_users'] ?? 0);
            }

            if ($total === 0) {
                remove_role($role_id);
            }
        }
    }

    public static function refresh_role_display_names(): void
    {
        $short = PluginSettings::app_short_name();
        self::ensure_role(self::ROLE_COORDINATOR, $short . ' Coordinator');
        self::ensure_role(self::ROLE_REVIEWER, $short . ' Reviewer');
    }

    private static function ensure_role(string $role_id, string $label): void
    {
        if (!function_exists('get_role') || !function_exists('add_role')) {
            return;
        }

        if (get_role($role_id) === null) {
            add_role($role_id, $label, []);

            return;
        }

        if (!function_exists('wp_roles')) {
            return;
        }

        $roles = wp_roles();
        if (!isset($roles->roles[$role_id])) {
            return;
        }

        $roles->roles[$role_id]['name'] = $label;
        if (isset($roles->role_names) && is_array($roles->role_names)) {
            $roles->role_names[$role_id] = $label;
        }
    }
}
