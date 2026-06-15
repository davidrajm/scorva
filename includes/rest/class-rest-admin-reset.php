<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Install;

/**
 * POST /scorva/v1/admin/reset
 *
 * Truncates all Scorva tables and deletes plugin options.
 * Requires both pr_manage_settings AND manage_options (admin-only guard).
 */
final class Rest_Admin_Reset
{
    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/admin/reset',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => [self::class, 'check_permission'],
                'args'                => [
                    'confirmation' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    public static function check_permission(): bool|\WP_Error
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS) || !current_user_can('manage_options')) {
            return new \WP_Error(
                'pr_forbidden',
                __('You do not have permission to perform a data reset.', 'scorva'),
                ['status' => 403]
            );
        }

        return true;
    }

    public static function handle(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $confirmation = (string) $request->get_param('confirmation');
        if ($confirmation !== 'RESET') {
            return new \WP_Error(
                'pr_reset_confirmation',
                __('Confirmation value must be exactly "RESET".', 'scorva'),
                ['status' => 400]
            );
        }

        global $wpdb;
        $prefix  = (string) $wpdb->prefix;
        $tables  = Install::get_pr_table_names($prefix);
        $options = Install::get_uninstall_option_names();

        $tables_cleared  = [];
        $options_deleted = [];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query('TRUNCATE TABLE `' . $table . '`');
            $tables_cleared[] = $table;
        }

        foreach ($options as $key) {
            delete_option($key);
            $options_deleted[] = $key;
        }

        error_log(sprintf(
            '[Scorva] Data reset performed by user %d at %s. Tables: %s',
            (int) get_current_user_id(),
            gmdate('c'),
            implode(', ', $tables_cleared)
        ));

        return new \WP_REST_Response(
            [
                'success'        => true,
                'tables_cleared' => $tables_cleared,
                'options_deleted' => $options_deleted,
            ],
            200
        );
    }
}
