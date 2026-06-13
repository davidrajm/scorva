<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\SessionCloseService;

final class Rest_Session_Close
{
    public static function register_routes(): void
    {
        $preview = Rest_Auth::with_rest_nonce(
            Rest_Auth::require_any_cap([PR_CAP_CLOSE_SESSION, PR_CAP_MANAGE_SESSIONS])
        );
        $write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_CLOSE_SESSION));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/close-preview',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'close_preview'],
                'permission_callback' => $preview,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/close',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'close_session'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reopen',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'reopen_session'],
                'permission_callback' => $write,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function close_preview(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $preview = (new SessionCloseService())->close_preview($session_id);

        if ($preview === null) {
            return new \WP_Error(
                'pr_session_not_found',
                __('Project not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return $preview;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function close_session(\WP_REST_Request $request)
    {
        $session_id = (int) $request->get_param('id');
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $also_disable = !empty($params['also_disable_coordinators']);
        $result = (new SessionCloseService())->close($session_id, $also_disable);

        if (!$result['ok']) {
            $error = $result['error'] ?? 'close_failed';
            $status = $error === 'session_not_found' ? 404 : 400;

            return new \WP_Error($error, __('Unable to close project.', 'scorva'), ['status' => $status]);
        }

        return [
            'session' => $result['session'] ?? null,
            'disabled_user_ids' => $result['disabled_user_ids'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function reopen_session(\WP_REST_Request $request)
    {
        $session_id = (int) $request->get_param('id');
        $result = (new SessionCloseService())->reopen($session_id);

        if (!$result['ok']) {
            $error = $result['error'] ?? 'reopen_failed';
            $status = $error === 'session_not_found' ? 404 : 400;

            return new \WP_Error($error, __('Unable to reopen project.', 'scorva'), ['status' => $status]);
        }

        return [
            'session' => $result['session'] ?? null,
            'reenabled_user_ids' => $result['reenabled_user_ids'] ?? [],
        ];
    }
}
