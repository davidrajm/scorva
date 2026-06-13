<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Services\MarkService;

final class Rest_Marks
{
    public static function register_routes(): void
    {
        $read_marks = Rest_Auth::allow_reviewer_session(static function (): bool|\WP_Error {
            $logged_in = Rest_Auth::require_logged_in()();
            if ($logged_in instanceof \WP_Error) {
                return $logged_in;
            }

            if (current_user_can(PR_CAP_ENTER_MARKS) || current_user_can(PR_CAP_MANAGE_SESSIONS)) {
                return true;
            }

            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view marks.', 'scorva'),
                ['status' => 403]
            );
        });
        $write_marks = Rest_Auth::allow_reviewer_session(
            Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ENTER_MARKS))
        );
        $override = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_OVERRIDE_MARKS));
        $manage_sessions = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/students/(?P<student_id>\d+)/marks',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_marks'],
                    'permission_callback' => $read_marks,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'save_marks'],
                    'permission_callback' => $write_marks,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/students/(?P<student_id>\d+)/attendance',
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'correct_attendance'],
                'permission_callback' => $manage_sessions,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/marks/(?P<id>\d+)/override',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'override_mark'],
                'permission_callback' => $override,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_marks(\WP_REST_Request $request)
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $student_id = (int) $request->get_param('student_id');
        $actor = Rest_Auth::current_actor_id();
        $coordinator = Rest_Auth::reviewer_session_context() === null
            && function_exists('current_user_can')
            && current_user_can(PR_CAP_MANAGE_SESSIONS);

        return (new MarkService())->get_marks($session_id, $review_id, $student_id, $actor, $coordinator);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function save_marks(\WP_REST_Request $request)
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $student_id = (int) $request->get_param('student_id');
        $actor = Rest_Auth::current_actor_id();

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $status = (string) ($params['status'] ?? MarkRepository::STATUS_DRAFT);
        $criteria = is_array($params['criteria'] ?? null) ? $params['criteria'] : [];
        $attendance_status = isset($params['attendance_status'])
            ? (string) $params['attendance_status']
            : null;

        return (new MarkService())->save_marks(
            $session_id,
            $review_id,
            $student_id,
            $actor,
            $criteria,
            $status,
            $attendance_status
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function correct_attendance(\WP_REST_Request $request)
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $student_id = (int) $request->get_param('student_id');
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $attendance_status = isset($params['attendance_status'])
            ? (string) $params['attendance_status']
            : '';
        $reason = trim((string) ($params['reason'] ?? ''));

        return (new MarkService())->correct_attendance_by_coordinator(
            $session_id,
            $review_id,
            $student_id,
            $attendance_status,
            $reason,
            function_exists('get_current_user_id') ? (int) get_current_user_id() : 0
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function override_mark(\WP_REST_Request $request)
    {
        $mark_id = (int) $request->get_param('id');
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $score = isset($params['score']) ? (float) $params['score'] : null;
        $reason = trim((string) ($params['reason'] ?? ''));

        if ($score === null) {
            return new \WP_Error('pr_invalid_score', __('Score is required.', 'scorva'), ['status' => 400]);
        }

        $service = new MarkService();
        $validation = $service->validate_override_reason($reason);
        if (!$validation['ok']) {
            $code = $validation['error'] ?? 'reason_invalid';
            $message = $code === 'reason_too_short'
                ? __('Reason must be at least 10 characters.', 'scorva')
                : __('Override reason is required.', 'scorva');

            return new \WP_Error($code, $message, ['status' => 400]);
        }

        $result = $service->override_mark(
            $mark_id,
            $score,
            $reason,
            function_exists('get_current_user_id') ? (int) get_current_user_id() : 0
        );

        if (!$result['ok']) {
            $error = $result['error'] ?? 'override_failed';
            $status = $error === 'mark_not_found' ? 404 : 400;

            return new \WP_Error($error, __('Unable to override mark.', 'scorva'), ['status' => $status]);
        }

        return ['mark' => $result['mark'] ?? null];
    }
}
