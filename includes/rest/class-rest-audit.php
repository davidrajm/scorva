<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\AuditService;

final class Rest_Audit
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::require_cap(PR_CAP_VIEW_REPORTS);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/audit',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_session_audit'],
                'permission_callback' => $read,
            ]
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function list_session_audit(\WP_REST_Request $request): array
    {
        $session_id = (int) $request->get_param('id');
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = max(1, min(100, (int) ($request->get_param('per_page') ?? 50)));

        return (new AuditService())->list_for_session($session_id, $page, $per_page);
    }
}
