<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Services\MarkService;

final class Rest_Reviewer_Unfreeze_Requests
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::require_cap(PR_CAP_ENTER_MARKS);
        $write = Rest_Auth::with_rest_nonce($read);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/unfreeze-requests',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_requests'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/unfreeze-requests/(?P<id>\d+)/grant',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'grant_request'],
                'permission_callback' => $write,
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

        $repo = new UnfreezeRequestRepository();

        return [
            'requests' => $repo->list_pending_for_panel_head((int) get_current_user_id()),
        ];
    }

    /**
     * @return array{granted: bool, marks_reverted: int}|\WP_Error
     */
    public static function grant_request(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');

        return (new MarkService())->grant_unfreeze($id, (int) get_current_user_id());
    }
}
