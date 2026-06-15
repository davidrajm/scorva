<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Services\MarkService;

final class Rest_Unfreeze_Requests
{
    public static function register_routes(): void
    {
        $manage = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/unfreeze-requests',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_requests'],
                'permission_callback' => Rest_Auth::with_rest_nonce(
                    Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS)
                ),
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/unfreeze-requests/(?P<id>\d+)/grant',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'grant_request'],
                'permission_callback' => $manage,
            ]
        );
    }

    /**
     * @return array{requests: list<array<string, mixed>>}
     */
    public static function list_requests(\WP_REST_Request $request): array
    {
        $status = (string) ($request->get_param('status') ?? 'pending');
        if ($status !== UnfreezeRequestRepository::STATUS_PENDING) {
            return ['requests' => []];
        }

        return ['requests' => (new UnfreezeRequestRepository())->list_pending_for_coordinator()];
    }

    /**
     * @return array{granted: bool, marks_reverted: int}|\WP_Error
     */
    public static function grant_request(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');

        return (new MarkService())->grant_unfreeze_by_coordinator($id, (int) get_current_user_id());
    }
}
