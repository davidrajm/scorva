<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Emails\RubricOpenEmail;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\RubricLifecycleService;

final class Rest_Reviews
{
    public static function register_routes(): void
    {
        $manage_cap = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $confirm_cap = Rest_Auth::with_rest_nonce(
            Rest_Auth::require_any_cap([PR_CAP_CONFIRM_RUBRICS, PR_CAP_MANAGE_SESSIONS])
        );
        $weights_cap = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_CONFIGURE_WEIGHTS));
        $read_cap = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_reviews'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_review'],
                    'permission_callback' => $manage_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_review'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_review'],
                    'permission_callback' => $manage_cap,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_review'],
                    'permission_callback' => $manage_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/criteria',
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'save_criteria'],
                'permission_callback' => $manage_cap,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/confirm',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'confirm_review'],
                'permission_callback' => $confirm_cap,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/unlock',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'unlock_review'],
                'permission_callback' => $confirm_cap,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/marks',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_marks'],
                'permission_callback' => $read_cap,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/weights',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_weights'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'save_weights'],
                    'permission_callback' => $weights_cap,
                ],
            ]
        );
    }

    /**
     * @return array{reviews: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_reviews(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = self::session_id_from_request($request);
        if ($session_id instanceof \WP_Error) {
            return $session_id;
        }

        $repository = new ReviewRepository();
        $reviews = [];
        foreach ($repository->list_for_session($session_id) as $review) {
            $reviews[] = self::format_review($repository, $review);
        }

        return ['reviews' => $reviews];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_review(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = self::session_id_from_request($request);
        if ($session_id instanceof \WP_Error) {
            return $session_id;
        }

        $session_error = self::require_session($session_id);
        if ($session_error instanceof \WP_Error) {
            return $session_error;
        }

        $repository = new ReviewRepository();
        $existing = $repository->list_for_session($session_id);

        $body = self::request_body($request);

        $label = trim((string) ($body['label'] ?? ''));
        if ($label === '') {
            $label = 'Review ' . (count($existing) + 1);
        }

        $review_id = $repository->create($session_id, [
            'label' => $label,
            'sort_order' => (int) ($body['sort_order'] ?? count($existing)),
        ]);

        $criteria = $body['criteria'] ?? null;
        if (is_array($criteria) && $criteria !== []) {
            if (!self::review_is_editable($repository, $review_id)) {
                return self::rubric_locked_error($repository, $review_id);
            }
            $repository->replace_criteria($review_id, $criteria);
        }

        $review = $repository->find_by_id($review_id);

        return self::format_review($repository, $review ?? []);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        return self::format_review($context['repository'], $context['review']);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $repository = $context['repository'];
        $review_id = (int) $context['review']['id'];

        $body = self::request_body($request);
        $updates = [];
        $formats = [];

        $label = $body['label'] ?? null;
        if (is_string($label) && trim($label) !== '') {
            $updates['label'] = trim($label);
            $formats[] = '%s';
        }

        if (array_key_exists('sort_order', $body)) {
            $updates['sort_order'] = (int) $body['sort_order'];
            $formats[] = '%d';
        }

        if (array_key_exists('marking_active', $body)) {
            if ($repository->is_coordinator_marks_locked($review_id)) {
                return new \WP_Error(
                    'coordinator_marks_locked',
                    __('Review marks are frozen. Unlock on Reports before changing marking status.', 'project-reviews'),
                    ['status' => 403]
                );
            }

            $updates['marking_active'] = !empty($body['marking_active']) ? 1 : 0;
            $formats[] = '%d';
        }

        if ($updates !== []) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'pr_reviews',
                $updates,
                ['id' => $review_id],
                $formats,
                ['%d']
            );
        }

        $review = $repository->find_by_id($review_id);

        return self::format_review($repository, $review ?? []);
    }

    /**
     * @return array{deleted: bool}|\WP_Error
     */
    public static function delete_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $repository = $context['repository'];
        $review = $context['review'];
        $review_id = (int) $review['id'];
        $session_id = (int) ($review['session_id'] ?? 0);

        if ($repository->count_for_session($session_id) <= 1) {
            return new \WP_Error(
                'pr_review_last_round',
                __('Cannot remove the only review round in this project.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $marks = new MarkRepository();
        $entered_scores = $marks->count_entered_scores_for_review($review_id) > 0;
        $body = self::request_body($request);
        $phrase = isset($body['confirm_label']) ? trim((string) $body['confirm_label']) : '';

        if ($entered_scores) {
            $expected = trim((string) ($review['label'] ?? ''));
            if ($expected === '' || $phrase !== $expected) {
                return new \WP_Error(
                    'pr_review_delete_confirmation_required',
                    __('Type the exact review round name to delete marks and remove this round.', 'project-reviews'),
                    ['status' => 400]
                );
            }
        }

        $marks->delete_all_for_review($review_id);
        (new PanelFreezeRepository())->delete_all_for_review($review_id);
        (new PanelUnfreezeRequestRepository())->delete_all_for_review($review_id);
        (new UnfreezeRequestRepository())->delete_all_for_review($review_id);

        $repository->delete($review_id);

        return ['deleted' => true];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function save_criteria(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $repository = $context['repository'];
        $review_id = (int) $context['review']['id'];

        if (!self::review_is_editable($repository, $review_id)) {
            return self::rubric_locked_error($repository, $review_id);
        }

        $body = self::request_body($request);
        $criteria = $body['criteria'] ?? null;
        if (!is_array($criteria)) {
            return new \WP_Error(
                'pr_invalid_criteria',
                __('Criteria must be an array.', 'project-reviews'),
                ['status' => 400]
            );
        }

        try {
            RubricLifecycleService::assert_valid_criteria_rows($criteria);
        } catch (\InvalidArgumentException $exception) {
            return new \WP_Error(
                'pr_invalid_criteria',
                $exception->getMessage(),
                ['status' => 400]
            );
        }

        $repository->replace_criteria($review_id, $criteria);
        $review = $repository->find_by_id($review_id);

        return self::format_review($repository, $review ?? []);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function confirm_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $review_id = (int) $context['review']['id'];
        $body = self::request_body($request);
        $mark_action = isset($body['mark_action']) && is_string($body['mark_action'])
            ? $body['mark_action']
            : null;

        try {
            $lifecycle = new RubricLifecycleService($context['repository']);
            $result = $lifecycle->confirm($review_id, $mark_action);
        } catch (\InvalidArgumentException $exception) {
            return new \WP_Error(
                'pr_rubric_confirm_failed',
                $exception->getMessage(),
                ['status' => 400]
            );
        }

        $review = $context['repository']->find_by_id($review_id);
        if (is_array($review)) {
            $session_id = (int) ($context['session_id'] ?? $review['session_id'] ?? 0);
            $session = (new SessionRepository())->find_by_id($session_id);
            if ($session !== null) {
                RubricOpenEmail::send_for_review($session, $review);
            }
        }

        return [
            'review' => self::format_review($context['repository'], $review ?? []),
            'lifecycle' => $result,
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function unlock_review(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $review_id = (int) $context['review']['id'];

        try {
            (new RubricLifecycleService($context['repository']))->unlock($review_id);
        } catch (\InvalidArgumentException $exception) {
            return new \WP_Error(
                'pr_rubric_unlock_failed',
                $exception->getMessage(),
                ['status' => 400]
            );
        }

        $review = $context['repository']->find_by_id($review_id);

        return [
            'review' => self::format_review($context['repository'], $review ?? []),
        ];
    }

    /**
     * @return array{marks: list<array<string, mixed>>, has_marks: bool}|\WP_Error
     */
    public static function list_marks(\WP_REST_Request $request): array|\WP_Error
    {
        $context = self::review_context($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $review_id = (int) $context['review']['id'];
        $marks = array_map(
            static fn (array $mark): array => [
                'id' => (int) ($mark['id'] ?? 0),
                'student_id' => (int) ($mark['student_id'] ?? 0),
                'reviewer_user_id' => (int) ($mark['reviewer_user_id'] ?? 0),
                'criterion_id' => (int) ($mark['criterion_id'] ?? 0),
                'score' => isset($mark['score']) ? (float) $mark['score'] : null,
                'flagged' => (int) ($mark['flagged'] ?? 0) === 1,
                'status' => (string) ($mark['status'] ?? 'draft'),
            ],
            $context['repository']->list_marks_for_review($review_id)
        );

        return [
            'marks' => $marks,
            'has_marks' => $marks !== [],
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_weights(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = self::session_id_from_request($request);
        if ($session_id instanceof \WP_Error) {
            return $session_id;
        }

        $session_error = self::require_session($session_id);
        if ($session_error instanceof \WP_Error) {
            return $session_error;
        }

        $repository = new ReviewRepository();
        $weights = $repository->list_session_weights($session_id);

        return [
            'review_weights' => $weights['review_weights'],
            'reviewer_weights' => $weights['reviewer_weights'],
            'has_marks' => $repository->session_has_marks($session_id),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function save_weights(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = self::session_id_from_request($request);
        if ($session_id instanceof \WP_Error) {
            return $session_id;
        }

        $session_error = self::require_session($session_id);
        if ($session_error instanceof \WP_Error) {
            return $session_error;
        }

        $repository = new ReviewRepository();
        $body = self::request_body($request);
        $review_weights = $body['review_weights'] ?? null;
        if (is_array($review_weights)) {
            foreach ($review_weights as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $review_id = (int) ($row['review_id'] ?? 0);
                if ($review_id <= 0 || !$repository->belongs_to_session($review_id, $session_id)) {
                    continue;
                }
                $repository->set_review_weight(
                    $session_id,
                    $review_id,
                    (float) ($row['weight'] ?? 1)
                );
            }
        }

        $reviewer_weights = $body['reviewer_weights'] ?? null;
        if (is_array($reviewer_weights)) {
            foreach ($reviewer_weights as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $review_id = (int) ($row['review_id'] ?? 0);
                $reviewer_user_id = (int) ($row['reviewer_user_id'] ?? 0);
                if ($review_id <= 0 || $reviewer_user_id <= 0) {
                    continue;
                }
                if (!$repository->belongs_to_session($review_id, $session_id)) {
                    continue;
                }
                $repository->set_reviewer_weight(
                    $review_id,
                    $reviewer_user_id,
                    (float) ($row['weight'] ?? 1)
                );
            }
        }

        return self::get_weights($request);
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private static function format_review(ReviewRepository $repository, array $review): array
    {
        $review_id = (int) ($review['id'] ?? 0);
        $session_id = (int) ($review['session_id'] ?? 0);
        $marks_repo = new MarkRepository();
        $criteria = array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'max_marks' => (float) ($row['max_marks'] ?? 0),
                'weight' => (float) ($row['weight'] ?? 1),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ],
            $repository->list_criteria($review_id)
        );

        $coordinator_locked = (int) ($review['coordinator_marks_locked'] ?? 0) === 1;
        $lock_readiness = (new MarkService())->review_lock_readiness($review_id);

        return [
            'id' => $review_id,
            'session_id' => $session_id,
            'label' => (string) ($review['label'] ?? ''),
            'sort_order' => (int) ($review['sort_order'] ?? 0),
            'status' => (string) ($review['status'] ?? ReviewRepository::STATUS_DRAFT),
            'marking_active' => (int) ($review['marking_active'] ?? 0) === 1,
            'coordinator_marks_locked' => $coordinator_locked,
            'review_lock_ready' => !$coordinator_locked && $lock_readiness['review_lock_ready'],
            'unfrozen_panels' => $lock_readiness['unfrozen_panels'],
            'criteria' => $criteria,
            'review_weight' => $repository->get_review_weight($session_id, $review_id),
            'has_marks' => $repository->count_marks_for_review($review_id) > 0,
            'has_entered_scores' => $marks_repo->count_entered_scores_for_review($review_id) > 0,
            'flagged_marks_count' => $repository->count_flagged_marks_for_review($review_id),
            'marking_allowed' => (string) ($review['status'] ?? '') === ReviewRepository::STATUS_CONFIRMED,
            'criteria_editable' => self::review_is_editable($repository, $review_id),
        ];
    }

    private static function review_is_editable(ReviewRepository $repository, int $review_id): bool
    {
        $review = $repository->find_by_id($review_id);
        if ($review === null) {
            return false;
        }

        $status = (string) ($review['status'] ?? '');

        if (in_array($status, [ReviewRepository::STATUS_DRAFT, ReviewRepository::STATUS_UNLOCKED], true)) {
            return true;
        }

        if ($status === ReviewRepository::STATUS_CONFIRMED) {
            return $repository->count_marks_for_review($review_id) === 0;
        }

        return false;
    }

    private static function rubric_locked_error(ReviewRepository $repository, int $review_id): \WP_Error
    {
        $has_marks = $repository->count_marks_for_review($review_id) > 0;
        $message = $has_marks
            ? __(
                'Criteria cannot be edited after scoring has started. Unlock the rubric to make changes.',
                'project-reviews'
            )
            : __('Criteria cannot be edited while the rubric is confirmed.', 'project-reviews');

        return new \WP_Error('pr_rubric_locked', $message, ['status' => 409]);
    }

    /**
     * @return array{repository: ReviewRepository, review: array<string, mixed>}|\WP_Error
     */
    private static function review_context(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = self::session_id_from_request($request);
        if ($session_id instanceof \WP_Error) {
            return $session_id;
        }

        $session_error = self::require_session($session_id);
        if ($session_error instanceof \WP_Error) {
            return $session_error;
        }

        $review_id = (int) $request->get_param('review_id');
        if ($review_id <= 0) {
            return new \WP_Error(
                'pr_invalid_review',
                __('Review id is required.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $repository = new ReviewRepository();
        if (!$repository->belongs_to_session($review_id, $session_id)) {
            return new \WP_Error(
                'pr_review_not_found',
                __('Review not found in this project.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $review = $repository->find_by_id($review_id);
        if ($review === null) {
            return new \WP_Error(
                'pr_review_not_found',
                __('Review not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        return [
            'repository' => $repository,
            'review' => $review,
            'session_id' => $session_id,
        ];
    }

    private static function session_id_from_request(\WP_REST_Request $request): int|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        if ($session_id <= 0) {
            return new \WP_Error(
                'pr_invalid_session',
                __('Project id is required.', 'project-reviews'),
                ['status' => 400]
            );
        }

        return $session_id;
    }

    /**
     * @return array<string, mixed>
     */
    private static function request_body(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : [];
    }

    private static function require_session(int $session_id): ?\WP_Error
    {
        $sessions = new SessionRepository();
        if ($sessions->find_by_id($session_id) === null) {
            return new \WP_Error(
                'pr_session_not_found',
                __('Project not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        return null;
    }
}
