<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Services\MarkService;

final class Rest_Reviewer_Assignments
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::allow_reviewer_session(Rest_Auth::require_cap(PR_CAP_ENTER_MARKS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/assignments',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_assignments'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/assignments/(?P<session_id>\d+)/(?P<review_id>\d+)/(?P<panel_id>\d+)/students',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_students'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/assignments/(?P<session_id>\d+)/(?P<review_id>\d+)/rubric',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_rubric'],
                'permission_callback' => $read,
            ]
        );

        $write = Rest_Auth::allow_reviewer_session(
            Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ENTER_MARKS))
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/assignments/(?P<session_id>\d+)/(?P<review_id>\d+)/freeze',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'freeze_marks'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/assignments/(?P<session_id>\d+)/(?P<review_id>\d+)/unfreeze-request',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'request_unfreeze'],
                'permission_callback' => $write,
            ]
        );
    }

    /**
     * @return array{assignments: list<array<string, mixed>>}
     */
    public static function list_assignments(\WP_REST_Request $request): array
    {
        unset($request);

        $user_id = Rest_Auth::current_actor_id();
        $sessions = new SessionRepository();
        $assignments_repo = new ReviewAssignmentRepository();
        $reviews = new ReviewRepository();

        $panels = new PanelRepository();
        $assignments = [];
        /** @var array<int, list<array<string, mixed>>> */
        $panel_reviewers_by_review = [];

        foreach ($sessions->list_all() as $session) {
            $session_id = (int) ($session['id'] ?? 0);
            $session_status = (string) ($session['status'] ?? '');
            if ($session_status === SessionRepository::STATUS_CLOSED) {
                continue;
            }

            foreach ($reviews->list_for_session($session_id) as $review) {
                $review_id = (int) ($review['id'] ?? 0);
                $review_status = (string) ($review['status'] ?? '');
                $marking_active = (int) ($review['marking_active'] ?? 0) === 1;

                $assignments_repo->ensure_assignments_from_session($review_id, $session_id);

                if (!isset($panel_reviewers_by_review[$review_id])) {
                    $panel_reviewers_by_review[$review_id] = $assignments_repo->list_panel_reviewers($review_id);
                }

                $panel_rows = $assignments_repo->panels_for_user($review_id, $session_id, $user_id);
                if ($panel_rows === []) {
                    continue;
                }

                $coordinator_locked = (int) ($review['coordinator_marks_locked'] ?? 0) === 1;
                $markable = $session_status === SessionRepository::STATUS_ACTIVE
                    && $review_status === ReviewRepository::STATUS_CONFIRMED
                    && $marking_active
                    && ! $coordinator_locked;
                $blocked_reason = self::blocked_reason(
                    $review_status,
                    $session_status,
                    $marking_active,
                    $coordinator_locked
                );

                foreach ($panel_rows as $panel) {
                    $panel_id = (int) ($panel['id'] ?? 0);
                    $assignments[] = [
                        'session_id' => $session_id,
                        'session_title' => (string) ($session['title'] ?? ''),
                        'review_id' => $review_id,
                        'review_label' => (string) ($review['label'] ?? ''),
                        'panel_id' => $panel_id,
                        'panel_name' => (string) ($panel['name'] ?? ''),
                        'markable' => $markable,
                        'blocked_reason' => $blocked_reason,
                        'is_panel_coordinator' => self::is_panel_coordinator(
                            $panel_reviewers_by_review[$review_id],
                            $panel_id,
                            $user_id
                        ),
                        'co_reviewers' => self::co_reviewers_for_panel(
                            $panels,
                            $panel_reviewers_by_review[$review_id],
                            $panel_id,
                            $user_id
                        ),
                    ];
                }
            }
        }

        return ['assignments' => $assignments];
    }

    /**
     * @return array{students: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_students(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $panel_id = (int) $request->get_param('panel_id');
        $user_id = Rest_Auth::current_actor_id();

        $sessions = new SessionRepository();
        $session = $sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        if ((string) ($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return new \WP_Error(
                'session_closed',
                __('This project is closed. Marking is no longer available.', 'project-reviews'),
                ['status' => 403]
            );
        }

        $reviews = new ReviewRepository();
        $review = $reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'project-reviews'), ['status' => 404]);
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            return new \WP_Error(
                'rubric_not_confirmed',
                __('The rubric for this review is not confirmed yet.', 'project-reviews'),
                ['status' => 403]
            );
        }

        if (!$reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'project-reviews'),
                ['status' => 403]
            );
        }

        if ($reviews->is_coordinator_marks_locked($review_id)) {
            return new \WP_Error(
                'coordinator_marks_locked',
                __('The coordinator locked marking for this review. No further mark changes are allowed.', 'project-reviews'),
                ['status' => 403]
            );
        }

        $assignments_repo = new ReviewAssignmentRepository();
        $panel_rows = $assignments_repo->panels_for_user($review_id, $session_id, $user_id);
        if ($panel_rows === []) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to this project.', 'project-reviews'),
                ['status' => 403]
            );
        }

        $panel_ids = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $panel_rows);
        if ($panel_id > 0) {
            if (!in_array($panel_id, $panel_ids, true)) {
                return new \WP_Error(
                    'not_assigned',
                    __('You are not assigned to this panel.', 'project-reviews'),
                    ['status' => 403]
                );
            }
            $panel_ids = [$panel_id];
        }

        $students_repo = new StudentRepository();
        $marks = new MarkRepository();
        $criteria = $reviews->list_criteria($review_id);
        $criteria_count = count($criteria);
        $criteria_payload = array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'max_marks' => (float) ($row['max_marks'] ?? 0),
            ],
            $criteria
        );

        $panels = new PanelRepository();
        $panel_name = '';
        foreach ($panels->list_by_session($session_id) as $panel) {
            if ((int) ($panel['id'] ?? 0) === $panel_id) {
                $panel_name = (string) ($panel['name'] ?? '');
                break;
            }
        }

        $students = [];
        foreach ($sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            $assignment = $assignments_repo->get_student_panel($review_id, $student_id);
            $student_panel_id = $assignment !== null
                ? (int) ($assignment['panel_id'] ?? 0)
                : (int) ($enrolment['panel_id'] ?? 0);

            if (!in_array($student_panel_id, $panel_ids, true)) {
                continue;
            }

            $student = $students_repo->find_by_id($student_id);
            if ($student === null) {
                continue;
            }

            $mark_rows = $marks->list_for_student_review($session_id, $review_id, $student_id, $user_id);
            $scores = [];
            $flagged = [];
            $coordinator_overridden = [];
            foreach ($criteria as $criterion) {
                $criterion_id = (int) ($criterion['id'] ?? 0);
                $scores[(string) $criterion_id] = null;
                $flagged[(string) $criterion_id] = false;
                $coordinator_overridden[(string) $criterion_id] = false;
            }
            foreach ($mark_rows as $mark) {
                $criterion_id = (int) ($mark['criterion_id'] ?? 0);
                if ($criterion_id <= 0) {
                    continue;
                }
                $scores[(string) $criterion_id] = $mark['score'] !== null
                    ? (float) $mark['score']
                    : null;
                $flagged[(string) $criterion_id] = (bool) (int) ($mark['flagged'] ?? 0);
                $coordinator_overridden[(string) $criterion_id] = (bool) (int) ($mark['coordinator_overridden'] ?? 0);
            }

            $frozen = $marks->is_student_frozen_for_reviewer(
                $session_id,
                $review_id,
                $student_id,
                $user_id,
                $criteria_count
            );

            $status = 'not_started';
            if ($frozen) {
                $status = 'frozen';
            } elseif ($mark_rows !== []) {
                $status = 'draft';
            }

            $students[] = [
                'id' => $student_id,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'attendance_status' => $assignments_repo->get_attendance_status($review_id, $student_id),
                'mark_status' => $status,
                'scores' => (object) $scores,
                'flagged' => (object) $flagged,
                'coordinator_overridden' => (object) $coordinator_overridden,
                'overridden_from_score' => (object) self::overridden_from_scores_for_marks($mark_rows),
            ];
        }

        usort(
            $students,
            static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $review_frozen = $students !== [] && !in_array(
            false,
            array_map(
                static fn (array $row): bool => ($row['mark_status'] ?? '') === 'frozen',
                $students
            ),
            true
        );

        $unfreeze_request_status = null;
        if ($panel_id > 0) {
            $pending = (new UnfreezeRequestRepository())->find_pending_for_assignment(
                $session_id,
                $review_id,
                $panel_id,
                $user_id
            );
            if ($pending !== null) {
                $unfreeze_request_status = UnfreezeRequestRepository::STATUS_PENDING;
            }
        }

        $panel_scores_frozen = $panel_id > 0
            && (new PanelFreezeRepository())->is_frozen($review_id, $panel_id);

        return [
            'session_title' => (string) ($session['title'] ?? ''),
            'review_label' => (string) ($review['label'] ?? ''),
            'panel_name' => $panel_name,
            'criteria' => $criteria_payload,
            'review_frozen' => $review_frozen,
            'panel_scores_frozen' => $panel_scores_frozen,
            'coordinator_marks_locked' => $reviews->is_coordinator_marks_locked($review_id),
            'unfreeze_request_status' => $unfreeze_request_status,
            'students' => $students,
        ];
    }

    /**
     * @return array{id: int, status: string, requested_at: string}|\WP_Error
     */
    public static function request_unfreeze(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $panel_id = (int) ($body['panel_id'] ?? $request->get_param('panel_id') ?? 0);
        if ($panel_id <= 0) {
            return new \WP_Error(
                'invalid_panel',
                __('A panel is required to request unfreeze.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $service = new MarkService();
        $existing = (new UnfreezeRequestRepository())->find_pending_for_assignment(
            $session_id,
            $review_id,
            $panel_id,
            Rest_Auth::current_actor_id()
        );
        if ($existing !== null) {
            return [
                'id' => (int) ($existing['id'] ?? 0),
                'status' => (string) ($existing['status'] ?? UnfreezeRequestRepository::STATUS_PENDING),
                'reason' => (string) ($existing['reason'] ?? ''),
                'requested_at' => (string) ($existing['requested_at'] ?? ''),
            ];
        }

        $reason = trim((string) ($body['reason'] ?? ''));

        return $service->request_unfreeze(
            $session_id,
            $review_id,
            $panel_id,
            Rest_Auth::current_actor_id(),
            $reason
        );
    }

    /**
     * @return array{frozen: bool, students_updated: int}|\WP_Error
     */
    public static function freeze_marks(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $panel_id = (int) ($body['panel_id'] ?? $request->get_param('panel_id') ?? 0);
        if ($panel_id <= 0) {
            return new \WP_Error(
                'invalid_panel',
                __('A panel is required to freeze scores.', 'project-reviews'),
                ['status' => 400]
            );
        }

        return (new MarkService())->freeze_review_marks(
            $session_id,
            $review_id,
            $panel_id,
            Rest_Auth::current_actor_id()
        );
    }

    /**
     * @return array{criteria: list<array<string, mixed>>}|\WP_Error
     */
    public static function get_rubric(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');

        $reviews = new ReviewRepository();
        $review = $reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'project-reviews'), ['status' => 404]);
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            return new \WP_Error(
                'rubric_not_confirmed',
                __('The rubric for this review is not confirmed yet.', 'project-reviews'),
                ['status' => 403]
            );
        }

        if (!$reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'project-reviews'),
                ['status' => 403]
            );
        }

        $criteria = array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'max_marks' => (float) ($row['max_marks'] ?? 0),
                'weight' => (float) ($row['weight'] ?? 1),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ],
            $reviews->list_criteria($review_id)
        );

        return ['criteria' => $criteria];
    }

    /**
     * @param list<array<string, mixed>> $panel_reviewer_rows
     */
    private static function is_panel_coordinator(
        array $panel_reviewer_rows,
        int $panel_id,
        int $user_id
    ): bool {
        foreach ($panel_reviewer_rows as $row) {
            if ((int) ($row['panel_id'] ?? 0) !== $panel_id) {
                continue;
            }
            if ((int) ($row['user_id'] ?? 0) !== $user_id) {
                continue;
            }

            return (int) ($row['is_panel_head'] ?? 0) === 1;
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $panel_reviewer_rows
     * @return list<array{name: string, user_id: int}>
     */
    private static function co_reviewers_for_panel(
        PanelRepository $panels,
        array $panel_reviewer_rows,
        int $panel_id,
        int $exclude_user_id
    ): array {
        if ($panel_id <= 0) {
            return [];
        }

        $co_reviewers = [];
        foreach ($panel_reviewer_rows as $row) {
            if ((int) ($row['panel_id'] ?? 0) !== $panel_id) {
                continue;
            }

            $reviewer_user_id = (int) ($row['user_id'] ?? 0);
            if ($reviewer_user_id <= 0 || $reviewer_user_id === $exclude_user_id) {
                continue;
            }

            $name = $panels->display_name_for_user($panel_id, $reviewer_user_id);
            if ($name === '') {
                continue;
            }

            $co_reviewers[] = [
                'name' => $name,
                'user_id' => $reviewer_user_id,
            ];
        }

        usort(
            $co_reviewers,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return $co_reviewers;
    }

    /**
     * @param list<array<string, mixed>> $mark_rows
     * @return array<string, float|null>
     */
    private static function overridden_from_scores_for_marks(array $mark_rows): array
    {
        $map = [];
        foreach ($mark_rows as $mark) {
            $criterion_id = (int) ($mark['criterion_id'] ?? 0);
            if ($criterion_id <= 0) {
                continue;
            }
            $prior = $mark['overridden_from_score'] ?? null;
            $map[(string) $criterion_id] = $prior !== null && $prior !== ''
                ? (float) $prior
                : null;
        }

        return $map;
    }

    private static function blocked_reason(
        string $review_status,
        string $session_status,
        bool $marking_active,
        bool $coordinator_locked = false
    ): ?string {
        if ($session_status === SessionRepository::STATUS_CLOSED) {
            return 'session_closed';
        }

        if ($session_status === SessionRepository::STATUS_DRAFT) {
            return 'session_not_active';
        }

        if ($review_status !== ReviewRepository::STATUS_CONFIRMED) {
            return 'rubric_not_confirmed';
        }

        if ($coordinator_locked) {
            return 'coordinator_marks_locked';
        }

        if (!$marking_active) {
            return 'marking_inactive';
        }

        return null;
    }
}
