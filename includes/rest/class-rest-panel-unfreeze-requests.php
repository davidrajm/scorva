<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Services\PanelReportService;

final class Rest_Panel_Unfreeze_Requests
{
    public static function register_routes(): void
    {
        $manage = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/panel-unfreeze-requests',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_requests'],
                'permission_callback' => $manage,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/panel-unfreeze-requests/(?P<id>\d+)/grant',
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
        if ($status !== PanelUnfreezeRequestRepository::STATUS_PENDING) {
            return ['requests' => []];
        }

        $repo = new PanelUnfreezeRequestRepository();

        return ['requests' => $repo->list_pending_for_coordinator()];
    }

    /**
     * @return array{granted: bool, panel_unfrozen: bool}|\WP_Error
     */
    public static function grant_request(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');

        return (new PanelReportService())->grant_panel_unfreeze($id, (int) get_current_user_id());
    }
}
