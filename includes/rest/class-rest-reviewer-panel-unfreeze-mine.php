<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;

final class Rest_Reviewer_Panel_Unfreeze_Mine
{
    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/panel-unfreeze-requests/mine',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get'],
                'permission_callback' => Rest_Auth::allow_reviewer_session(
                    Rest_Auth::require_cap(PR_CAP_ENTER_MARKS)
                ),
            ]
        );
    }

    /**
     * @return array{requests: list<array<string, mixed>>}
     */
    public static function get(): array
    {
        $user_id = Rest_Auth::current_actor_id();

        return [
            'requests' => (new PanelUnfreezeRequestRepository())->list_for_requester($user_id),
        ];
    }
}
