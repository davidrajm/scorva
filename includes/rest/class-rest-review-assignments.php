<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class Rest_Review_Assignments
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);
        $write_panels = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_PANELS));
        $write_reviewers = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ASSIGN_REVIEWERS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_assignments'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments/students',
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'update_students'],
                'permission_callback' => $write_panels,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments/reviewers',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'add_reviewer'],
                    'permission_callback' => $write_reviewers,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_reviewer'],
                    'permission_callback' => $write_reviewers,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments/reviewers/(?P<user_id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'delete_reviewer'],
                'permission_callback' => $write_reviewers,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments/copy-from/(?P<source_review_id>\d+)',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'copy_from_review'],
                'permission_callback' => $write_panels,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/assignments/reset-to-session-defaults',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'reset_to_defaults'],
                'permission_callback' => $write_panels,
            ]
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function get_assignments(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $context['assignments']->ensure_assignments_from_session(
            $context['review_id'],
            $context['session_id']
        );

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function update_students(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $rows = $body['students'] ?? $body;
        if (!is_array($rows)) {
            return new \WP_Error(
                'pr_invalid_assignments',
                __('Students must be an array.', 'scorva'),
                ['status' => 400]
            );
        }

        $panels = new PanelRepository();
        $panel_ids = array_map(
            static fn (array $p): int => (int) ($p['id'] ?? 0),
            $panels->list_by_session($context['session_id'])
        );

        $payload = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($student_id <= 0 || $panel_id <= 0 || !in_array($panel_id, $panel_ids, true)) {
                continue;
            }
            $entry = ['student_id' => $student_id, 'panel_id' => $panel_id];
            if (array_key_exists('project_title', $row)) {
                $entry['project_title'] = (string) ($row['project_title'] ?? '');
            }
            $payload[] = $entry;
        }

        $context['assignments']->bulk_set_student_panels($context['review_id'], $payload);

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function add_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $panel_id = (int) ($body['panel_id'] ?? 0);
        $user_id = (int) ($body['user_id'] ?? 0);
        if ($panel_id <= 0 || $user_id <= 0) {
            return new \WP_Error(
                'pr_invalid_reviewer',
                __('panel_id and user_id are required.', 'scorva'),
                ['status' => 400]
            );
        }

        $context['assignments']->upsert_panel_reviewer(
            $context['review_id'],
            $panel_id,
            $user_id,
            (float) ($body['weight'] ?? 1)
        );

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function update_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $panel_id = (int) ($body['panel_id'] ?? 0);
        $user_id = (int) ($body['user_id'] ?? 0);
        if ($panel_id <= 0 || $user_id <= 0) {
            return new \WP_Error(
                'pr_invalid_reviewer',
                __('panel_id and user_id are required.', 'scorva'),
                ['status' => 400]
            );
        }

        $context['assignments']->upsert_panel_reviewer(
            $context['review_id'],
            $panel_id,
            $user_id,
            (float) ($body['weight'] ?? 1)
        );

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function delete_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $panel_id = (int) $request->get_param('panel_id');
        $user_id = (int) $request->get_param('user_id');
        if ($panel_id <= 0) {
            $body = $request->get_json_params();
            if (is_array($body)) {
                $panel_id = (int) ($body['panel_id'] ?? 0);
            }
        }

        if ($panel_id <= 0 || $user_id <= 0) {
            return new \WP_Error(
                'pr_invalid_reviewer',
                __('panel_id and user_id are required.', 'scorva'),
                ['status' => 400]
            );
        }

        $context['assignments']->delete_panel_reviewer($context['review_id'], $panel_id, $user_id);

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function copy_from_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $source_review_id = (int) $request->get_param('source_review_id');
        $reviews = new ReviewRepository();
        if (!$reviews->belongs_to_session($source_review_id, $context['session_id'])) {
            return new \WP_Error('pr_review_not_found', __('Source review not found.', 'scorva'), ['status' => 404]);
        }

        $context['assignments']->copy_from_review($source_review_id, $context['review_id']);

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function reset_to_defaults(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $context['assignments']->reset_to_session_defaults($context['review_id'], $context['session_id']);

        return self::format_assignments(
            $context['session_id'],
            $context['review_id'],
            $context['assignments']
        );
    }

    /**
     * @return array{
     *     session_id: int,
     *     review_id: int,
     *     assignments: ReviewAssignmentRepository
     * }|\WP_Error
     */
    private static function review_context(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');

        $sessions = new SessionRepository();
        if ($sessions->find_by_id($session_id) === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $reviews = new ReviewRepository();
        if (!$reviews->belongs_to_session($review_id, $session_id)) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        return [
            'session_id' => $session_id,
            'review_id' => $review_id,
            'assignments' => new ReviewAssignmentRepository(),
        ];
    }

    /**
     * @return array{students: list<array<string, mixed>>, reviewers: list<array<string, mixed>>}
     */
    private static function format_assignments(
        int $session_id,
        int $review_id,
        ReviewAssignmentRepository $assignments
    ): array {
        $sessions = new SessionRepository();
        $students_repo = new StudentRepository();
        $panels = new PanelRepository();

        $panel_names = [];
        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_names[(int) ($panel['id'] ?? 0)] = (string) ($panel['name'] ?? '');
        }

        $student_rows = [];
        foreach ($assignments->list_student_panels($review_id) as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            $student = $students_repo->find_by_id($student_id);
            if ($student === null) {
                continue;
            }
            $student_rows[] = [
                'student_id' => $student_id,
                'panel_id' => $panel_id,
                'panel_name' => $panel_names[$panel_id] ?? '',
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'project_title' => $assignments->resolve_project_title($session_id, $review_id, $student_id),
                'attendance_status' => $assignments->get_attendance_status($review_id, $student_id),
            ];
        }

        usort(
            $student_rows,
            static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $reviewer_rows = [];
        foreach ($assignments->list_panel_reviewers($review_id) as $row) {
            $panel_id = (int) ($row['panel_id'] ?? 0);
            $user_id = (int) ($row['user_id'] ?? 0);
            $name = '';
            foreach ($panels->list_reviewers($panel_id) as $session_reviewer) {
                if ((int) ($session_reviewer['user_id'] ?? 0) === $user_id) {
                    $name = (string) ($session_reviewer['name'] ?? '');
                    break;
                }
            }
            $reviewer_rows[] = [
                'panel_id' => $panel_id,
                'panel_name' => $panel_names[$panel_id] ?? '',
                'user_id' => $user_id,
                'name' => $name,
                'weight' => (float) ($row['weight'] ?? 1),
            ];
        }

        return [
            'students' => $student_rows,
            'reviewers' => $reviewer_rows,
        ];
    }
}
