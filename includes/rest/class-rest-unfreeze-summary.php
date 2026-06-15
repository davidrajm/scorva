<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;

final class Rest_Unfreeze_Summary
{
    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/unfreeze-summary',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get'],
                'permission_callback' => Rest_Auth::with_rest_nonce(
                    Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS)
                ),
            ]
        );
    }

    /**
     * @return array{reviewer_pending: int, panel_pending: int}
     */
    public static function get(): array
    {
        return [
            'reviewer_pending' => count((new UnfreezeRequestRepository())->list_pending_for_coordinator()),
            'panel_pending' => count((new PanelUnfreezeRequestRepository())->list_pending_for_coordinator()),
        ];
    }
}
