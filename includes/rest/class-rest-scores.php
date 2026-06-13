<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\ScoreService;

final class Rest_Scores
{
    public static function register_routes(): void
    {
        $read = static function (): bool|\WP_Error {
            $logged_in = Rest_Auth::require_logged_in()();
            if ($logged_in instanceof \WP_Error) {
                return $logged_in;
            }

            if (current_user_can(PR_CAP_MANAGE_SESSIONS)
                || current_user_can(PR_CAP_ENTER_MARKS)
                || current_user_can(PR_CAP_VIEW_REPORTS)) {
                return true;
            }

            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view scores.', 'scorva'),
                ['status' => 403]
            );
        };

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/students/(?P<student_id>\d+)/scores',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_student_scores'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/students/(?P<student_id>\d+)/reviews/(?P<review_id>\d+)/scores',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_review_scores'],
                'permission_callback' => $read,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_student_scores(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $student_id = (int) $request->get_param('student_id');

        $scope = self::assert_score_access($session_id, $student_id, 0);
        if ($scope instanceof \WP_Error) {
            return $scope;
        }

        $service = new ScoreService();
        $breakdown = $service->get_student_breakdown($session_id, $student_id);

        return [
            'session_id' => $session_id,
            'student_id' => $student_id,
            'combined_score' => $breakdown['combined_score'],
            'reviews' => $breakdown['reviews'],
            'read_only' => true,
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_review_scores(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $student_id = (int) $request->get_param('student_id');
        $review_id = (int) $request->get_param('review_id');

        $scope = self::assert_score_access($session_id, $student_id, $review_id);
        if ($scope instanceof \WP_Error) {
            return $scope;
        }

        $service = new ScoreService();
        $aggregate = $service->calculate_review_score($session_id, $student_id, $review_id);
        $combined = $service->calculate_combined_score($session_id, $student_id);

        return [
            'session_id' => $session_id,
            'student_id' => $student_id,
            'review_id' => $review_id,
            'review_score' => $aggregate['review_score'],
            'reviewers' => $aggregate['reviewers'],
            'combined_score' => $combined['combined_score'],
            'read_only' => true,
        ];
    }

    private static function assert_score_access(
        int $session_id,
        int $student_id,
        int $review_id
    ): bool|\WP_Error {
        if (current_user_can(PR_CAP_MANAGE_SESSIONS) || current_user_can(PR_CAP_VIEW_REPORTS)) {
            return true;
        }

        if (!current_user_can(PR_CAP_ENTER_MARKS)) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view scores.', 'scorva'),
                ['status' => 403]
            );
        }

        $actor = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $marks = new MarkService();

        if ($review_id > 0) {
            if (!$marks->is_reviewer_assigned($session_id, $review_id, $student_id, $actor)) {
                return new \WP_Error(
                    'not_assigned',
                    __('You are not assigned to view scores for this student.', 'scorva'),
                    ['status' => 403]
                );
            }

            return true;
        }

        $reviews = new \ProjectReviews\Repositories\ReviewRepository();
        foreach ($reviews->list_for_session($session_id) as $review) {
            $rid = (int) ($review['id'] ?? 0);
            if ($marks->is_reviewer_assigned($session_id, $rid, $student_id, $actor)) {
                return true;
            }
        }

        return new \WP_Error(
            'not_assigned',
            __('You are not assigned to view scores for this student.', 'scorva'),
            ['status' => 403]
        );
    }
}
