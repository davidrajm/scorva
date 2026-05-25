<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\ScoreService;

final class Rest_Progress
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/progress',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_progress'],
                'permission_callback' => $read,
            ]
        );
    }

    /**
     * Each review includes mark-grain summary (marks_completed, marks_in_progress,
     * marks_not_started, marks_total, percent) plus student-grain students_* counts.
     * Panel objects include the same summary fields scoped to that panel.
     *
     * @return array{reviews: list<array<string, mixed>>}
     */
    public static function get_progress(\WP_REST_Request $request): array
    {
        $session_id = (int) $request->get_param('session_id');
        $service = new ScoreService();

        return [
            'reviews' => $service->calculate_session_progress($session_id),
        ];
    }
}
