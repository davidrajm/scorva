<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
final class PanelReportService
{
    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private ReviewAssignmentRepository $assignments;

    private PanelRepository $panels;

    private MarkRepository $marks;

    private PanelFreezeRepository $freezes;

    private PanelUnfreezeRequestRepository $panel_unfreeze_requests;

    private ReportsViewService $reports;

    public function __construct(
        ?SessionRepository $sessions = null,
        ?ReviewRepository $reviews = null,
        ?ReviewAssignmentRepository $assignments = null,
        ?PanelRepository $panels = null,
        ?MarkRepository $marks = null,
        ?PanelFreezeRepository $freezes = null,
        ?PanelUnfreezeRequestRepository $panel_unfreeze_requests = null,
        ?ReportsViewService $reports = null
    ) {
        $this->sessions = $sessions ?? new SessionRepository();
        $this->reviews = $reviews ?? new ReviewRepository();
        $this->assignments = $assignments ?? new ReviewAssignmentRepository();
        $this->panels = $panels ?? new PanelRepository();
        $this->marks = $marks ?? new MarkRepository();
        $this->freezes = $freezes ?? new PanelFreezeRepository();
        $this->panel_unfreeze_requests = $panel_unfreeze_requests ?? new PanelUnfreezeRequestRepository();
        $this->reports = $reports ?? new ReportsViewService();
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function get_report(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $user_id
    ): array|\WP_Error {
        $guard = $this->assert_panel_coordinator($session_id, $review_id, $panel_id, $user_id);
        if ($guard instanceof \WP_Error) {
            return $guard;
        }

        $report = $this->reports->scores_matrix_for_panel($session_id, $review_id, $panel_id);
        if ($report instanceof \WP_Error) {
            return $report;
        }

        $pending = $this->panel_unfreeze_requests->find_pending_for_panel($session_id, $review_id, $panel_id);
        $report['panel_unfreeze_request_status'] = $pending !== null
            ? PanelUnfreezeRequestRepository::STATUS_PENDING
            : null;
        $report['panel_report_settings_frozen'] = SessionPanelReportSettings::is_settings_frozen($session_id);
        $settings = SessionPanelReportSettings::get($session_id);
        $report['panel_report_settings_frozen_at'] = (string) ($settings['settings_frozen_at'] ?? '');

        return $report;
    }

    /**
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    public function generate_pdf(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $user_id
    ): array|\WP_Error {
        $report = $this->get_report($session_id, $review_id, $panel_id, $user_id);
        if ($report instanceof \WP_Error) {
            return $report;
        }

        if (!SessionPanelReportSettings::is_settings_frozen($session_id)) {
            return new \WP_Error(
                'panel_report_settings_not_frozen',
                __('The project coordinator must freeze panel report settings before downloading the PDF.', 'scorva'),
                ['status' => 403]
            );
        }

        $pdf_service = new PanelReportPdfService();

        return $pdf_service->render($report);
    }

    /**
     * Blank institutional scoring sheet for coordinator printing (no marks, no panel-head gate).
     *
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    public function generate_offline_scoring_pdf(
        int $session_id,
        int $review_id,
        int $panel_id
    ): array|\WP_Error {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id($panel_id);
        if ($panel === null || (int) ($panel['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error(
                'pr_panel_not_found',
                __('Panel not found in this project.', 'scorva'),
                ['status' => 404]
            );
        }

        $report = $this->reports->scores_matrix_for_panel($session_id, $review_id, $panel_id);
        if ($report instanceof \WP_Error) {
            return $report;
        }

        $students = is_array($report['students'] ?? null) ? $report['students'] : [];
        if ($students === []) {
            return new \WP_Error(
                'offline_scoring_no_students',
                __('This panel has no enrolled students for this review.', 'scorva'),
                ['status' => 400]
            );
        }

        $report['offline_scoring'] = true;

        return (new PanelReportPdfService())->render_offline_scoring_multi([$report]);
    }

    /**
     * Blank offline scoring sheets for all panels in a review (single PDF, page break per panel).
     *
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    public function generate_offline_scoring_pdf_for_review(
        int $session_id,
        int $review_id
    ): array|\WP_Error {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            return new \WP_Error(
                'rubric_not_confirmed',
                __('The rubric for this review is not confirmed yet.', 'scorva'),
                ['status' => 403]
            );
        }

        $reports = [];
        foreach ($this->panel_ids_with_students_for_review($review_id) as $panel_id) {
            $report = $this->reports->scores_matrix_for_panel($session_id, $review_id, $panel_id);
            if ($report instanceof \WP_Error) {
                return $report;
            }

            $students = is_array($report['students'] ?? null) ? $report['students'] : [];
            if ($students === []) {
                continue;
            }

            $reports[] = $report;
        }

        if ($reports === []) {
            return new \WP_Error(
                'offline_scoring_no_panels',
                __('No panels with enrolled students were found for this review.', 'scorva'),
                ['status' => 400]
            );
        }

        return (new PanelReportPdfService())->render_offline_scoring_multi($reports);
    }

    /**
     * @return list<int>
     */
    private function panel_ids_with_students_for_review(int $review_id): array
    {
        $panel_ids = [];
        foreach ($this->assignments->list_student_panels($review_id) as $row) {
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $panel_ids[$panel_id] = true;
            }
        }

        if ($panel_ids === []) {
            return [];
        }

        $sorted = [];
        foreach (array_keys($panel_ids) as $panel_id) {
            $panel = $this->panels->find_by_id($panel_id);
            $sorted[] = [
                'id' => $panel_id,
                'name' => (string) ($panel['name'] ?? ''),
            ];
        }

        usort(
            $sorted,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
                ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0))
        );

        return array_values(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $sorted));
    }

    /**
     * @return array{frozen: bool, frozen_at: string}|\WP_Error
     */
    public function freeze_panel(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $user_id
    ): array|\WP_Error {
        $guard = $this->assert_panel_coordinator($session_id, $review_id, $panel_id, $user_id);
        if ($guard instanceof \WP_Error) {
            return $guard;
        }

        if ($this->freezes->is_frozen($review_id, $panel_id)) {
            return new \WP_Error(
                'panel_scores_frozen',
                __('This panel is already frozen.', 'scorva'),
                ['status' => 403]
            );
        }

        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        if ((string) ($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return new \WP_Error(
                'session_closed',
                __('This project is closed. Marking is no longer allowed.', 'scorva'),
                ['status' => 403]
            );
        }

        if ((string) ($session['status'] ?? '') !== SessionRepository::STATUS_ACTIVE) {
            return new \WP_Error(
                'session_not_active',
                __('This project is not active yet. Marking is not open.', 'scorva'),
                ['status' => 403]
            );
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            return new \WP_Error(
                'rubric_not_confirmed',
                __('The rubric for this review is not confirmed yet.', 'scorva'),
                ['status' => 403]
            );
        }

        if ($this->reviews->is_coordinator_marks_locked($review_id)) {
            return new \WP_Error(
                'coordinator_marks_locked',
                __('The coordinator locked marking for this review. No further mark changes are allowed.', 'scorva'),
                ['status' => 403]
            );
        }

        if (!$this->reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'scorva'),
                ['status' => 403]
            );
        }

        $criteria = $this->reviews->list_criteria($review_id);
        $criteria_count = count($criteria);
        if ($criteria_count === 0) {
            return new \WP_Error(
                'invalid_criterion',
                __('This review has no rubric criteria.', 'scorva'),
                ['status' => 400]
            );
        }

        $student_ids = $this->student_ids_for_panel($session_id, $review_id, $panel_id);
        $panel_reviewers = $this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id);
        $reviewer_user_ids = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['user_id'] ?? 0),
            $panel_reviewers
        )));

        if ($student_ids === [] || $reviewer_user_ids === []) {
            return new \WP_Error(
                'panel_freeze_incomplete',
                __('Cannot freeze: this panel has no students or reviewers assigned.', 'scorva'),
                ['status' => 400]
            );
        }

        $readiness = $this->panel_freeze_readiness_error(
            $session_id,
            $review_id,
            $panel_id,
            $student_ids,
            $reviewer_user_ids,
            $criteria,
            $criteria_count
        );
        if ($readiness instanceof \WP_Error) {
            return $readiness;
        }

        $this->freezes->freeze($review_id, $panel_id, $user_id);

        $freeze = $this->freezes->find($review_id, $panel_id);

        return [
            'frozen' => true,
            'frozen_at' => (string) ($freeze['frozen_at'] ?? ''),
        ];
    }

    public function is_panel_frozen(int $review_id, int $panel_id): bool
    {
        return $this->freezes->is_frozen($review_id, $panel_id);
    }

    /**
     * @return array{id: int, status: string, requested_at: string}|\WP_Error
     */
    public function request_panel_unfreeze(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $user_id,
        string $reason
    ): array|\WP_Error {
        $guard = $this->assert_panel_coordinator($session_id, $review_id, $panel_id, $user_id);
        if ($guard instanceof \WP_Error) {
            return $guard;
        }

        if (!$this->freezes->is_frozen($review_id, $panel_id)) {
            return new \WP_Error(
                'panel_not_frozen',
                __('This panel is not frozen, so a panel unfreeze request is not needed.', 'scorva'),
                ['status' => 400]
            );
        }

        $reason = trim($reason);
        if ($reason === '') {
            return new \WP_Error(
                'unfreeze_reason_required',
                __('Please explain why the panel should be unfrozen.', 'scorva'),
                ['status' => 400]
            );
        }

        if (strlen($reason) > 500) {
            return new \WP_Error(
                'unfreeze_reason_too_long',
                __('Reason must be 500 characters or fewer.', 'scorva'),
                ['status' => 400]
            );
        }

        $row = $this->panel_unfreeze_requests->create_pending(
            $session_id,
            $review_id,
            $panel_id,
            $user_id,
            $reason
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'status' => (string) ($row['status'] ?? PanelUnfreezeRequestRepository::STATUS_PENDING),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
        ];
    }

    /**
     * @return array{granted: bool, panel_unfrozen: bool}|\WP_Error
     */
    public function grant_panel_unfreeze(int $request_id, int $coordinator_user_id): array|\WP_Error
    {
        $request = $this->panel_unfreeze_requests->find_by_id($request_id);
        if ($request === null) {
            return new \WP_Error(
                'panel_unfreeze_request_not_found',
                __('Panel unfreeze request not found.', 'scorva'),
                ['status' => 404]
            );
        }

        if ((string) ($request['status'] ?? '') !== PanelUnfreezeRequestRepository::STATUS_PENDING) {
            return new \WP_Error(
                'panel_unfreeze_request_not_pending',
                __('This panel unfreeze request is no longer pending.', 'scorva'),
                ['status' => 400]
            );
        }

        $session_id = (int) ($request['session_id'] ?? 0);
        $review_id = (int) ($request['review_id'] ?? 0);
        $panel_id = (int) ($request['panel_id'] ?? 0);

        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        $unfrozen = $this->freezes->unfreeze($review_id, $panel_id);

        $granted = $this->panel_unfreeze_requests->grant($request_id, $coordinator_user_id);
        if ($granted === null) {
            return new \WP_Error(
                'panel_unfreeze_request_not_pending',
                __('This panel unfreeze request is no longer pending.', 'scorva'),
                ['status' => 400]
            );
        }

        $audit = new AuditService();
        $audit->log(
            'panel_unfreeze_granted',
            'panel_unfreeze_request',
            $request_id,
            null,
            json_encode(
                [
                    'session_id' => $session_id,
                    'review_id' => $review_id,
                    'panel_id' => $panel_id,
                ],
                JSON_THROW_ON_ERROR
            ),
            $coordinator_user_id
        );

        return [
            'granted' => true,
            'panel_unfrozen' => $unfrozen,
        ];
    }

    /**
     * @return true|\WP_Error
     */
    public function assert_panel_coordinator(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $user_id
    ): bool|\WP_Error {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id($panel_id);
        if ($panel === null || (int) ($panel['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error(
                'pr_panel_not_found',
                __('Panel not found in this project.', 'scorva'),
                ['status' => 404]
            );
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to this panel.', 'scorva'),
                ['status' => 403]
            );
        }

        if (!$this->assignments->is_panel_head_for_user($review_id, $panel_id, $user_id)) {
            return new \WP_Error(
                'not_panel_coordinator',
                __('Only the panel coordinator can access this panel report.', 'scorva'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @param list<int> $student_ids
     * @param list<int> $reviewer_user_ids
     * @param list<array<string, mixed>> $criteria
     */
    private function panel_freeze_readiness_error(
        int $session_id,
        int $review_id,
        int $panel_id,
        array $student_ids,
        array $reviewer_user_ids,
        array $criteria,
        int $criteria_count
    ): ?\WP_Error {
        $mark_service = new MarkService(
            $this->sessions,
            $this->reviews,
            $this->assignments,
            $this->marks
        );

        $reviewers_missing_marks = [];
        $reviewers_not_frozen = [];

        foreach ($reviewer_user_ids as $reviewer_user_id) {
            if ($reviewer_user_id <= 0) {
                continue;
            }

            $has_incomplete_marks = false;
            $has_unfrozen_marks = false;

            foreach ($student_ids as $student_id) {
                if ($this->assignments->get_attendance_status($review_id, $student_id)
                    === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
                    continue;
                }

                if (!$mark_service->is_student_marking_complete(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewer_user_id,
                    $criteria
                )) {
                    $has_incomplete_marks = true;
                }

                if (!$this->marks->is_student_frozen_for_reviewer(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewer_user_id,
                    $criteria_count
                )) {
                    $has_unfrozen_marks = true;
                }
            }

            $display_name = $this->panels->display_name_for_user($panel_id, $reviewer_user_id);
            if ($display_name === '') {
                $display_name = sprintf(
                    /* translators: %d: WordPress user id */
                    __('Reviewer #%d', 'scorva'),
                    $reviewer_user_id
                );
            }

            if ($has_incomplete_marks) {
                $reviewers_missing_marks[] = $display_name;
            } elseif ($has_unfrozen_marks) {
                $reviewers_not_frozen[] = $display_name;
            }
        }

        if ($reviewers_missing_marks !== []) {
            $names = $this->format_name_list($reviewers_missing_marks);

            return new \WP_Error(
                'panel_freeze_incomplete_marks',
                count($reviewers_missing_marks) === 1
                    ? sprintf(
                        /* translators: %s: reviewer display name */
                        __('Cannot freeze the panel yet: %s still has students without a score on every criterion.', 'scorva'),
                        $names
                    )
                    : sprintf(
                        /* translators: %s: comma-separated reviewer names */
                        __('Cannot freeze the panel yet: %s still have students without a score on every criterion.', 'scorva'),
                        $names
                    ),
                [
                    'status' => 400,
                    'reviewers' => $reviewers_missing_marks,
                ]
            );
        }

        if ($reviewers_not_frozen !== []) {
            $names = $this->format_name_list($reviewers_not_frozen);

            return new \WP_Error(
                'panel_freeze_reviewers_not_frozen',
                count($reviewers_not_frozen) === 1
                    ? sprintf(
                        /* translators: %s: reviewer display name */
                        __('Cannot freeze the panel yet: %s must freeze their personal scores for this review first. Ask them to use Freeze on their marking grid.', 'scorva'),
                        $names
                    )
                    : sprintf(
                        /* translators: %s: comma-separated reviewer names */
                        __('Cannot freeze the panel yet: %s must each freeze their personal scores for this review first. Ask them to use Freeze on their marking grid.', 'scorva'),
                        $names
                    ),
                [
                    'status' => 400,
                    'reviewers' => $reviewers_not_frozen,
                ]
            );
        }

        return null;
    }

    /**
     * @param list<string> $names
     */
    private function format_name_list(array $names): string
    {
        $names = array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
        if ($names === []) {
            return '';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) === 2) {
            return sprintf(
                /* translators: 1: first name, 2: second name */
                __('%1$s and %2$s', 'scorva'),
                $names[0],
                $names[1]
            );
        }

        $last = array_pop($names);

        return sprintf(
            /* translators: 1: comma-separated names, 2: final name */
            __('%1$s, and %2$s', 'scorva'),
            implode(', ', $names),
            $last
        );
    }

    /**
     * @return list<int>
     */
    private function student_ids_for_panel(int $session_id, int $review_id, int $panel_id): array
    {
        $ids = [];
        foreach ($this->assignments->list_student_panels($review_id) as $row) {
            if ((int) ($row['panel_id'] ?? 0) !== $panel_id) {
                continue;
            }
            $student_id = (int) ($row['student_id'] ?? 0);
            if ($student_id > 0) {
                $ids[] = $student_id;
            }
        }

        if ($ids !== []) {
            sort($ids);

            return $ids;
        }

        foreach ($this->sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }
            $assignment = $this->assignments->get_student_panel($review_id, $student_id);
            $student_panel_id = $assignment !== null
                ? (int) ($assignment['panel_id'] ?? 0)
                : (int) ($enrolment['panel_id'] ?? 0);
            if ($student_panel_id === $panel_id) {
                $ids[] = $student_id;
            }
        }

        sort($ids);

        return $ids;
    }
}
