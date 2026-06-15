<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Rest_Admin_Roles
{
    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/admin/users',
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'handle_search'],
                'permission_callback' => Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS),
                'args'                => [
                    'search' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static fn ($v): bool => is_string($v) && strlen(trim($v)) >= 2,
                    ],
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/admin/users/(?P<user_id>\d+)/role',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle_assign_role'],
                'permission_callback' => Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS),
                'args'                => [
                    'user_id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => static fn ($v): bool => (int) $v > 0,
                    ],
                ],
            ]
        );
    }

    /**
     * GET /scorva/v1/admin/users?search=…
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle_search(\WP_REST_Request $request)
    {
        $q = (string) $request->get_param('search');

        $results = get_users([
            'search'         => '*' . $q . '*',
            'search_columns' => ['display_name', 'user_email', 'user_login'],
            'number'         => 8,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
        ]);

        $scorva_roles = [
            Capabilities::ROLE_COORDINATOR,
            Capabilities::ROLE_HOD,
            Capabilities::ROLE_REVIEWER,
        ];

        $payload = [];
        foreach ($results as $user) {
            $scorva_role = null;
            foreach ($scorva_roles as $role) {
                if (in_array($role, (array) $user->roles, true)) {
                    $scorva_role = $role;
                    break;
                }
            }

            $payload[] = [
                'id'          => $user->ID,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'scorva_role' => $scorva_role,
            ];
        }

        return new \WP_REST_Response($payload, 200);
    }

    /**
     * POST /scorva/v1/admin/users/{user_id}/role
     * Body: { "role": "project_reviews_coordinator" | "project_reviews_hod" | "project_reviews_reviewer" | null }
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle_assign_role(\WP_REST_Request $request)
    {
        $user_id = (int) $request->get_param('user_id');
        $user    = get_userdata($user_id);

        if ($user === false) {
            return new \WP_Error('scorva_user_not_found', __('User not found.', 'scorva'), ['status' => 404]);
        }

        // Administrators and super-admins are managed by WordPress core — skip.
        if ($user->has_cap('delete_users') || (function_exists('is_super_admin') && is_super_admin($user_id))) {
            return new \WP_Error(
                'scorva_admin_protected',
                __('WordPress administrators cannot have Scorva roles managed from this page.', 'scorva'),
                ['status' => 403]
            );
        }

        $body          = $request->get_json_params();
        $requested_role = isset($body['role']) ? ($body['role'] === null ? null : (string) $body['role']) : null;

        $valid_roles = [
            Capabilities::ROLE_COORDINATOR,
            Capabilities::ROLE_HOD,
            Capabilities::ROLE_REVIEWER,
        ];

        if ($requested_role !== null && !in_array($requested_role, $valid_roles, true)) {
            return new \WP_Error(
                'scorva_invalid_role',
                __('Invalid Scorva role.', 'scorva'),
                ['status' => 400]
            );
        }

        // Remove all Scorva roles first.
        foreach ($valid_roles as $role) {
            $user->remove_role($role);
        }

        // Add the new role if one was requested.
        if ($requested_role !== null) {
            $user->add_role($requested_role);
        }

        return new \WP_REST_Response(
            [
                'success' => true,
                'user_id' => $user_id,
                'role'    => $requested_role,
            ],
            200
        );
    }
}
