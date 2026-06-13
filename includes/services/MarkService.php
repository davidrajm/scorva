<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;

final class MarkService
{
    private const SCORE_STEP = 0.5;

    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private ReviewAssignmentRepository $assignments;

    private MarkRepository $marks;

    private UnfreezeRequestRepository $unfreeze_requests;

    public function __construct(
        ?SessionRepository $sessions = null,
        ?ReviewRepository $reviews = null,
        ?ReviewAssignmentRepository $assignments = null,
        ?MarkRepository $marks = null,
        ?UnfreezeRequestRepository $unfreeze_requests = null
    ) {
        $this->sessions = $sessions ?? new SessionRepository();
        $this->reviews = $reviews ?? new ReviewRepository();
        $this->assignments = $assignments ?? new ReviewAssignmentRepository();
        $this->marks = $marks ?? new MarkRepository();
        $this->unfreeze_requests = $unfreeze_requests ?? new UnfreezeRequestRepository();
    }

    /**
     * @param list<array{criterion_id: int, score: float|int|string|null}> $criteria
     * @return array{marks: list<array<string, mixed>>}|\WP_Error
     */
    public function save_marks(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria,
        string $status,
        ?string $attendance_status = null
    ): array|\WP_Error {
        $guard = $this->validate_save_context($session_id, $review_id, $student_id, $reviewer_user_id);
        if ($guard instanceof \WP_Error) {
            return $guard;
        }

        $attendance_validation = $this->validate_attendance_status($attendance_status);
        if ($attendance_validation instanceof \WP_Error) {
            return $attendance_validation;
        }
        $attendance_status = $attendance_validation;

        $panel_frozen_error = $this->panel_frozen_error_if_frozen(
            $session_id,
            $review_id,
            $student_id
        );
        if ($panel_frozen_error instanceof \WP_Error) {
            return $panel_frozen_error;
        }

        $rubric_criteria = $this->reviews->list_criteria($review_id);
        if ($this->marks->is_student_frozen_for_reviewer(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            count($rubric_criteria)
        )) {
            return new \WP_Error(
                'marks_frozen',
                __('Scores are frozen for this review. Contact your coordinator if you need changes.', 'scorva'),
                ['status' => 403]
            );
        }

        $consensus_error = $this->validate_attendance_consensus(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            $attendance_status
        );
        if ($consensus_error instanceof \WP_Error) {
            return $consensus_error;
        }

        $this->assignments->upsert_reviewer_attendance_assertion(
            $review_id,
            $student_id,
            $reviewer_user_id,
            $attendance_status
        );

        $persist_attendance = $this->persist_attendance_status(
            $session_id,
            $review_id,
            $student_id,
            $attendance_status
        );
        if ($persist_attendance instanceof \WP_Error) {
            return $persist_attendance;
        }

        $status = $status === MarkRepository::STATUS_SUBMITTED
            ? MarkRepository::STATUS_SUBMITTED
            : MarkRepository::STATUS_DRAFT;

        $criteria_by_id = [];
        foreach ($rubric_criteria as $row) {
            $criteria_by_id[(int) $row['id']] = $row;
        }

        if ($status === MarkRepository::STATUS_SUBMITTED && count($criteria_by_id) === 0) {
            return new \WP_Error(
                'invalid_criterion',
                __('This review has no rubric criteria.', 'scorva'),
                ['status' => 400]
            );
        }

        if ($attendance_status === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            return $this->save_absent_marks(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criteria_by_id,
                $status
            );
        }

        $payload_by_id = [];
        foreach ($criteria as $entry) {
            $criterion_id = (int) ($entry['criterion_id'] ?? 0);
            if ($criterion_id <= 0) {
                return new \WP_Error(
                    'invalid_criterion',
                    __('Each mark must reference a rubric criterion.', 'scorva'),
                    ['status' => 400]
                );
            }
            if (!isset($criteria_by_id[$criterion_id])) {
                return new \WP_Error(
                    'invalid_criterion',
                    __('One or more criteria are not part of this rubric.', 'scorva'),
                    ['status' => 400]
                );
            }
            $payload_by_id[$criterion_id] = $entry;
        }

        if ($status === MarkRepository::STATUS_SUBMITTED) {
            foreach ($criteria_by_id as $criterion_id => $criterion) {
                if (!isset($payload_by_id[$criterion_id])) {
                    return new \WP_Error(
                        'invalid_score',
                        __('All rubric criteria must have a score before submitting.', 'scorva'),
                        ['status' => 400]
                    );
                }
                $normalized = $this->normalize_score($payload_by_id[$criterion_id]['score'] ?? null);
                if ($normalized === null) {
                    return new \WP_Error(
                        'invalid_score',
                        __('All rubric criteria must have a valid numeric score before submitting.', 'scorva'),
                        ['status' => 400]
                    );
                }
            }
        }

        $saved = [];
        foreach ($payload_by_id as $criterion_id => $entry) {
            $criterion = $criteria_by_id[$criterion_id];
            $score = $this->normalize_score($entry['score'] ?? null);
            if ($score === null && $status === MarkRepository::STATUS_SUBMITTED) {
                return new \WP_Error(
                    'invalid_score',
                    __('All rubric criteria must have a valid numeric score before submitting.', 'scorva'),
                    ['status' => 400]
                );
            }
            if ($score !== null) {
                $validation = $this->validate_score($score, (float) ($criterion['max_marks'] ?? 0));
                if ($validation instanceof \WP_Error) {
                    return $validation;
                }
            }

            $mark_id = $this->marks->upsert(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criterion_id,
                $score,
                $status
            );

            $saved[] = [
                'id' => $mark_id,
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => $student_id,
                'reviewer_user_id' => $reviewer_user_id,
                'criterion_id' => $criterion_id,
                'score' => $score,
                'status' => $status,
                'flagged' => false,
            ];
        }

        return [
            'marks' => $saved,
            'attendance_status' => $attendance_status,
        ];
    }

    /**
     * @return array{marks: list<array<string, mixed>>}|\WP_Error
     */
    public function get_marks(
        int $session_id,
        int $review_id,
        int $student_id,
        int $actor_user_id,
        bool $coordinator_scope
    ): array|\WP_Error {
        if (!$this->reviews->belongs_to_session($review_id, $session_id)) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        if (!$coordinator_scope) {
            $guard = $this->validate_assignment($session_id, $review_id, $student_id, $actor_user_id);
            if ($guard instanceof \WP_Error) {
                return $guard;
            }
            $rows = $this->marks->list_for_student_review($session_id, $review_id, $student_id, $actor_user_id);
        } else {
            $rows = $this->marks->list_for_student_review($session_id, $review_id, $student_id);
        }

        return [
            'marks' => array_map([$this, 'format_mark'], $rows),
            'attendance_status' => $this->assignments->get_attendance_status($review_id, $student_id),
        ];
    }

    public function is_reviewer_assigned(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id
    ): bool {
        return !$this->validate_assignment($session_id, $review_id, $student_id, $reviewer_user_id) instanceof \WP_Error;
    }

    private function validate_save_context(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id
    ): ?\WP_Error {
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

        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return $lock_error;
        }

        if (!$this->reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'scorva'),
                ['status' => 403]
            );
        }

        $assignment_error = $this->validate_assignment($session_id, $review_id, $student_id, $reviewer_user_id);
        if ($assignment_error instanceof \WP_Error) {
            return $assignment_error;
        }

        return $this->panel_frozen_error_if_frozen($session_id, $review_id, $student_id);
    }

    private function panel_frozen_error_if_frozen(
        int $session_id,
        int $review_id,
        int $student_id
    ): ?\WP_Error {
        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        $panel_id = $assignment !== null ? (int) ($assignment['panel_id'] ?? 0) : 0;
        if ($panel_id <= 0) {
            $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
        }
        if ($panel_id <= 0) {
            return null;
        }

        if (!(new PanelFreezeRepository())->is_frozen($review_id, $panel_id)) {
            return null;
        }

        return new \WP_Error(
            'panel_scores_frozen',
            __('The panel coordinator finalized scores for this panel. Marks cannot be changed.', 'scorva'),
            ['status' => 403]
        );
    }

    private function coordinator_lock_error_if_locked(int $review_id): ?\WP_Error
    {
        if (!$this->reviews->is_coordinator_marks_locked($review_id)) {
            return null;
        }

        return new \WP_Error(
            'coordinator_marks_locked',
            __('The coordinator locked marking for this review. No further mark changes are allowed.', 'scorva'),
            ['status' => 403]
        );
    }

    private function validate_assignment(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id
    ): ?\WP_Error {
        $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
        if ($enrolment === null) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to mark this student.', 'scorva'),
                ['status' => 403]
            );
        }

        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        $panel_id = $assignment !== null
            ? (int) ($assignment['panel_id'] ?? 0)
            : (int) ($enrolment['panel_id'] ?? 0);

        if ($panel_id <= 0) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to mark this student.', 'scorva'),
                ['status' => 403]
            );
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to mark this student.', 'scorva'),
                ['status' => 403]
            );
        }

        return null;
    }

    private function validate_score(float $score, float $max_marks): ?\WP_Error
    {
        if ($score < 0) {
            return new \WP_Error(
                'invalid_score',
                __('Score cannot be negative.', 'scorva'),
                ['status' => 400]
            );
        }

        if (!$this->is_valid_score_step($score)) {
            return new \WP_Error(
                'invalid_score',
                __('Enter a score in steps of 0.5 (e.g. 3, 3.5, 4).', 'scorva'),
                ['status' => 400]
            );
        }

        if ($max_marks > 0 && $score > $max_marks) {
            return new \WP_Error(
                'invalid_score',
                sprintf(
                    /* translators: %s: maximum marks for criterion */
                    __('Score cannot exceed %s.', 'scorva'),
                    (string) $max_marks
                ),
                ['status' => 400]
            );
        }

        return null;
    }

    private function is_valid_score_step(float $score): bool
    {
        $scaled = $score / self::SCORE_STEP;

        return abs($scaled - round($scaled)) < 1e-6;
    }

    /**
     * @param float|int|string|null $value
     */
    private function normalize_score($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function format_mark(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'session_id' => (int) ($row['session_id'] ?? 0),
            'review_id' => (int) ($row['review_id'] ?? 0),
            'student_id' => (int) ($row['student_id'] ?? 0),
            'reviewer_user_id' => (int) ($row['reviewer_user_id'] ?? 0),
            'criterion_id' => (int) ($row['criterion_id'] ?? 0),
            'score' => $row['score'] !== null ? (float) $row['score'] : null,
            'status' => (string) ($row['status'] ?? MarkRepository::STATUS_DRAFT),
            'flagged' => (bool) (int) ($row['flagged'] ?? 0),
            'coordinator_overridden' => (bool) (int) ($row['coordinator_overridden'] ?? 0),
            'overridden_from_score' => isset($row['overridden_from_score']) && $row['overridden_from_score'] !== null && $row['overridden_from_score'] !== ''
                ? (float) $row['overridden_from_score']
                : null,
        ];
    }

    /**
     * Coordinator override when panel consensus blocks a lone dissenting reviewer save.
     *
     * @return array{
     *     attendance_status: string,
     *     review_id: int,
     *     student_id: int,
     *     panel_id: int,
     *     reviewers_updated: int
     * }|\WP_Error
     */
    public function correct_attendance_by_coordinator(
        int $session_id,
        int $review_id,
        int $student_id,
        string $attendance_status,
        string $reason,
        int $actor_user_id
    ): array|\WP_Error {
        $reason_validation = $this->validate_override_reason($reason);
        if (!$reason_validation['ok']) {
            $code = $reason_validation['error'] ?? 'reason_required';

            return new \WP_Error(
                $code,
                $code === 'reason_too_short'
                    ? __('Reason must be at least 10 characters.', 'scorva')
                    : __('A reason is required for attendance correction.', 'scorva'),
                ['status' => 400]
            );
        }

        $attendance_validation = $this->validate_attendance_status($attendance_status);
        if ($attendance_validation instanceof \WP_Error) {
            return $attendance_validation;
        }
        $attendance_status = $attendance_validation;

        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        if ((string) ($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return new \WP_Error(
                'session_closed',
                __('This project is closed. Attendance cannot be changed.', 'scorva'),
                ['status' => 403]
            );
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        $coordinator_lock = $this->coordinator_lock_error_if_locked($review_id);
        if ($coordinator_lock instanceof \WP_Error) {
            return $coordinator_lock;
        }

        $panel_id = $this->resolve_student_panel_id($session_id, $review_id, $student_id);
        if ($panel_id <= 0) {
            return new \WP_Error(
                'not_assigned',
                __('This student is not assigned to a panel for this review.', 'scorva'),
                ['status' => 404]
            );
        }

        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        if ($assignment === null) {
            $this->assignments->set_student_panel($review_id, $student_id, $panel_id);
        }

        $old_status = $this->assignments->get_attendance_status($review_id, $student_id);

        $this->assignments->set_attendance_status($review_id, $student_id, $attendance_status);
        $reviewers_updated = $this->assignments->sync_panel_attendance_assertions(
            $review_id,
            $student_id,
            $panel_id,
            $attendance_status
        );

        if ($attendance_status === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            $this->null_panel_reviewer_marks_for_absent(
                $session_id,
                $review_id,
                $student_id,
                $panel_id
            );
        }

        $audit = new AuditService();
        $audit->log(
            'attendance_correction',
            'session',
            $session_id,
            $old_status,
            json_encode(
                [
                    'review_id' => $review_id,
                    'student_id' => $student_id,
                    'panel_id' => $panel_id,
                    'old_status' => $old_status,
                    'new_status' => $attendance_status,
                    'reason' => trim($reason),
                    'reviewers_updated' => $reviewers_updated,
                ],
                JSON_THROW_ON_ERROR
            ),
            $actor_user_id
        );

        return [
            'attendance_status' => $attendance_status,
            'review_id' => $review_id,
            'student_id' => $student_id,
            'panel_id' => $panel_id,
            'reviewers_updated' => $reviewers_updated,
        ];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function validate_override_reason(string $reason): array
    {
        $trimmed = trim($reason);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'reason_required'];
        }
        if (strlen($trimmed) < 10) {
            return ['ok' => false, 'error' => 'reason_too_short'];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, mark?: array<string, mixed>}
     */
    public function override_mark(int $mark_id, float $score, string $reason, int $actor_user_id): array
    {
        $validation = $this->validate_override_reason($reason);
        if (!$validation['ok']) {
            return $validation;
        }

        $existing = $this->marks->find_by_id($mark_id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'mark_not_found'];
        }

        $review_id = (int) ($existing['review_id'] ?? 0);
        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return ['ok' => false, 'error' => 'coordinator_marks_locked'];
        }

        $session_id = (int) ($existing['session_id'] ?? 0);
        $session = $this->sessions->find_by_id($session_id);
        if ($session !== null && (string) ($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return ['ok' => false, 'error' => 'session_closed'];
        }

        $criterion_id = (int) ($existing['criterion_id'] ?? 0);
        $criterion = null;
        foreach ($this->reviews->list_criteria((int) ($existing['review_id'] ?? 0)) as $row) {
            if ((int) ($row['id'] ?? 0) === $criterion_id) {
                $criterion = $row;
                break;
            }
        }
        if ($criterion !== null) {
            $score_error = $this->validate_score($score, (float) ($criterion['max_marks'] ?? 0));
            if ($score_error instanceof \WP_Error) {
                return ['ok' => false, 'error' => 'invalid_score'];
            }
        }

        $old = $existing['score'] !== null ? (string) $existing['score'] : null;
        $this->marks->apply_coordinator_override($mark_id, $score);

        $audit = new AuditService();
        $audit->log(
            'mark_override',
            'mark',
            $mark_id,
            $old,
            json_encode(['score' => $score, 'reason' => trim($reason)], JSON_THROW_ON_ERROR),
            $actor_user_id
        );

        $updated = $this->marks->find_by_id($mark_id);

        return ['ok' => true, 'mark' => $updated !== null ? $this->format_mark($updated) : null];
    }

    public function count_open_marks(int $session_id): int
    {
        return $this->marks->count_open_for_session($session_id);
    }

    /**
     * Finalize all marks for students on a panel assignment (bulk submitted).
     *
     * @return array{frozen: bool, students_updated: int}|\WP_Error
     */
    public function freeze_review_marks(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): array|\WP_Error {
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

        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return $lock_error;
        }

        if (!$this->reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'scorva'),
                ['status' => 403]
            );
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to this panel.', 'scorva'),
                ['status' => 403]
            );
        }

        if ((new PanelFreezeRepository())->is_frozen($review_id, $panel_id)) {
            return new \WP_Error(
                'panel_scores_frozen',
                __('The panel coordinator finalized scores for this panel. Marks cannot be changed.', 'scorva'),
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
        if ($student_ids === []) {
            return ['frozen' => true, 'students_updated' => 0];
        }

        $incomplete_details = [];
        foreach ($student_ids as $student_id) {
            $missing_criteria = $this->list_missing_criteria(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criteria
            );
            if ($missing_criteria === []) {
                continue;
            }

            $student = (new StudentRepository())->find_by_id($student_id);
            $incomplete_details[] = [
                'student_id' => $student_id,
                'student_name' => (string) ($student['name'] ?? ''),
                'student_reg_no' => (string) ($student['reg_no'] ?? ''),
                'missing_criteria' => $missing_criteria,
            ];
        }

        foreach ($student_ids as $student_id) {
            if ($this->assignments->get_attendance_status($review_id, $student_id)
                === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
                $this->ensure_absent_marks_draft(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewer_user_id,
                    $criteria
                );
            }
        }

        $incomplete = count($incomplete_details);
        if ($incomplete > 0) {
            return new \WP_Error(
                'incomplete_marks',
                $this->format_incomplete_marks_error_message($incomplete_details),
                [
                    'status' => 400,
                    'incomplete_count' => $incomplete,
                    'incomplete' => $incomplete_details,
                ]
            );
        }

        $updated = $this->marks->submit_for_students(
            $session_id,
            $review_id,
            $reviewer_user_id,
            $student_ids
        );

        return ['frozen' => true, 'students_updated' => count($student_ids)];
    }

    /**
     * @return array{id: int, status: string, requested_at: string}|\WP_Error
     */
    public function request_unfreeze(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id,
        string $reason
    ): array|\WP_Error {
        $guard = $this->validate_unfreeze_request_context($session_id, $review_id, $panel_id, $reviewer_user_id);
        if ($guard instanceof \WP_Error) {
            return $guard;
        }

        $reason = trim($reason);
        if ($reason === '') {
            return new \WP_Error(
                'unfreeze_reason_required',
                __('Please explain why you need to edit frozen scores.', 'scorva'),
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

        if (!$this->is_review_frozen_for_panel($session_id, $review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_frozen',
                __('Scores are not frozen for this assignment.', 'scorva'),
                ['status' => 400]
            );
        }

        if ((new PanelFreezeRepository())->is_frozen($review_id, $panel_id)) {
            return new \WP_Error(
                'panel_scores_frozen',
                __('Personal unfreeze is unavailable while the panel is frozen. Ask your panel coordinator to request a panel unfreeze from the project coordinator first.', 'scorva'),
                ['status' => 403]
            );
        }

        $row = $this->unfreeze_requests->create_pending(
            $session_id,
            $review_id,
            $panel_id,
            $reviewer_user_id,
            $reason
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'status' => (string) ($row['status'] ?? UnfreezeRequestRepository::STATUS_PENDING),
            'reason' => (string) ($row['reason'] ?? $reason),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
        ];
    }

    /**
     * @return array{granted: bool, marks_reverted: int}|\WP_Error
     */
    public function grant_unfreeze(int $request_id, int $panel_head_user_id): array|\WP_Error
    {
        $request = $this->unfreeze_requests->find_by_id($request_id);
        if ($request === null) {
            return new \WP_Error(
                'unfreeze_request_not_found',
                __('Unfreeze request not found.', 'scorva'),
                ['status' => 404]
            );
        }

        if ((string) ($request['status'] ?? '') !== UnfreezeRequestRepository::STATUS_PENDING) {
            return new \WP_Error(
                'unfreeze_request_not_pending',
                __('This unfreeze request is no longer pending.', 'scorva'),
                ['status' => 400]
            );
        }

        $session_id = (int) ($request['session_id'] ?? 0);
        $review_id = (int) ($request['review_id'] ?? 0);
        $panel_id = (int) ($request['panel_id'] ?? 0);
        $reviewer_user_id = (int) ($request['reviewer_user_id'] ?? 0);

        $head_error = $this->assert_panel_head_for_request($review_id, $panel_id, $panel_head_user_id);
        if ($head_error instanceof \WP_Error) {
            return $head_error;
        }

        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return $lock_error;
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('The reviewer is no longer assigned to this panel.', 'scorva'),
                ['status' => 400]
            );
        }

        $reverted = $this->unfreeze_review_marks($session_id, $review_id, $panel_id, $reviewer_user_id);
        if ($reverted instanceof \WP_Error) {
            return $reverted;
        }

        $granted = $this->unfreeze_requests->grant($request_id, $panel_head_user_id);
        if ($granted === null) {
            return new \WP_Error(
                'unfreeze_request_not_pending',
                __('This unfreeze request is no longer pending.', 'scorva'),
                ['status' => 400]
            );
        }

        $audit = new AuditService();
        $audit->log(
            'unfreeze_granted',
            'unfreeze_request',
            $request_id,
            null,
            json_encode(
                [
                    'session_id' => $session_id,
                    'review_id' => $review_id,
                    'panel_id' => $panel_id,
                    'reviewer_user_id' => $reviewer_user_id,
                ],
                JSON_THROW_ON_ERROR
            ),
            $panel_head_user_id
        );

        return [
            'granted' => true,
            'marks_reverted' => $reverted,
        ];
    }

    /**
     * @return int|\WP_Error Number of mark rows reverted to draft.
     */
    public function unfreeze_review_marks(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): int|\WP_Error {
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

        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return $lock_error;
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to this panel.', 'scorva'),
                ['status' => 403]
            );
        }

        $student_ids = $this->student_ids_for_panel($session_id, $review_id, $panel_id);

        return $this->marks->revert_to_draft_for_students(
            $session_id,
            $review_id,
            $reviewer_user_id,
            $student_ids
        );
    }

    public function is_review_frozen_for_panel(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): bool {
        $criteria = $this->reviews->list_criteria($review_id);
        $criteria_count = count($criteria);
        if ($criteria_count === 0) {
            return false;
        }

        $student_ids = $this->student_ids_for_panel($session_id, $review_id, $panel_id);
        if ($student_ids === []) {
            return false;
        }

        foreach ($student_ids as $student_id) {
            if (!$this->marks->is_student_frozen_for_reviewer(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criteria_count
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return true|\WP_Error
     */
    private function validate_unfreeze_request_context(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): true|\WP_Error {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        if ((string) ($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return new \WP_Error(
                'session_closed',
                __('This project is closed. Marking is no longer available.', 'scorva'),
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

        $lock_error = $this->coordinator_lock_error_if_locked($review_id);
        if ($lock_error instanceof \WP_Error) {
            return $lock_error;
        }

        if (!$this->reviews->is_marking_active($review_id)) {
            return new \WP_Error(
                'marking_inactive',
                __('This review round is not open for marking.', 'scorva'),
                ['status' => 403]
            );
        }

        if (!$this->assignments->is_reviewer_on_panel($review_id, $panel_id, $reviewer_user_id)) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to this panel.', 'scorva'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @return true|\WP_Error
     */
    private function assert_panel_head_for_request(int $review_id, int $panel_id, int $user_id): bool|\WP_Error
    {
        if (!$this->assignments->is_panel_head_for_user($review_id, $panel_id, $user_id)) {
            return new \WP_Error(
                'not_panel_coordinator',
                __('Only the panel coordinator for this panel can approve reviewer unfreeze requests.', 'scorva'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function participating_panel_ids(int $review_id): array
    {
        $panel_ids = [];
        foreach ($this->assignments->list_student_panels($review_id) as $row) {
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $panel_ids[$panel_id] = $panel_id;
            }
        }

        return array_values($panel_ids);
    }

    /**
     * @return array{review_lock_ready: bool, unfrozen_panels: list<array{id: int, name: string}>}
     */
    public function review_lock_readiness(int $review_id): array
    {
        $panel_ids = $this->participating_panel_ids($review_id);
        if ($panel_ids === []) {
            return [
                'review_lock_ready' => false,
                'unfrozen_panels' => [],
            ];
        }

        $freezes = new PanelFreezeRepository();
        $panels = new PanelRepository();
        $unfrozen = [];

        foreach ($panel_ids as $panel_id) {
            if ($freezes->is_frozen($review_id, $panel_id)) {
                continue;
            }

            $panel = $panels->find_by_id($panel_id);
            $unfrozen[] = [
                'id' => $panel_id,
                'name' => (string) ($panel['name'] ?? sprintf(__('Panel %d', 'scorva'), $panel_id)),
            ];
        }

        return [
            'review_lock_ready' => $unfrozen === [],
            'unfrozen_panels' => $unfrozen,
        ];
    }

    /**
     * @return true|\WP_Error
     */
    private function assert_all_panels_frozen_for_review_lock(int $review_id): bool|\WP_Error
    {
        $readiness = $this->review_lock_readiness($review_id);
        $panel_ids = $this->participating_panel_ids($review_id);

        if ($panel_ids === []) {
            return new \WP_Error(
                'no_panels_for_review_lock',
                __('Assign students to panels before freezing this review.', 'scorva'),
                ['status' => 400]
            );
        }

        if ($readiness['unfrozen_panels'] !== []) {
            return new \WP_Error(
                'panels_not_all_frozen',
                __('Every participating panel must freeze panel scores before you can freeze this review.', 'scorva'),
                [
                    'status' => 400,
                    'unfrozen_panels' => $readiness['unfrozen_panels'],
                ]
            );
        }

        return true;
    }

    /**
     * @return array{coordinator_marks_locked: bool}|\WP_Error
     */
    public function lock_review_marks(int $session_id, int $review_id, int $actor_user_id): array|\WP_Error
    {
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
                __('Only confirmed reviews can be locked.', 'scorva'),
                ['status' => 400]
            );
        }

        if ($this->reviews->is_coordinator_marks_locked($review_id)) {
            return ['coordinator_marks_locked' => true];
        }

        $ready = $this->assert_all_panels_frozen_for_review_lock($review_id);
        if ($ready instanceof \WP_Error) {
            return $ready;
        }

        $this->reviews->set_coordinator_marks_locked($review_id, true);
        $this->reviews->set_marking_active($review_id, false);

        $audit = new AuditService();
        $audit->log(
            'review_marks_locked',
            'review',
            $review_id,
            null,
            json_encode(
                [
                    'session_id' => $session_id,
                    'review_id' => $review_id,
                    'marking_active_cleared' => true,
                ],
                JSON_THROW_ON_ERROR
            ),
            $actor_user_id
        );

        return ['coordinator_marks_locked' => true];
    }

    /**
     * @return array{coordinator_marks_locked: bool, marking_active: bool}|\WP_Error
     */
    public function unlock_review_marks(int $session_id, int $review_id, int $actor_user_id): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'scorva'), ['status' => 404]);
        }

        $was_locked = $this->reviews->is_coordinator_marks_locked($review_id);

        if ($was_locked) {
            $this->reviews->set_coordinator_marks_locked($review_id, false);

            $session_active = (string) ($session['status'] ?? '') === SessionRepository::STATUS_ACTIVE;
            $review_confirmed = (string) ($review['status'] ?? '') === ReviewRepository::STATUS_CONFIRMED;
            if ($session_active && $review_confirmed) {
                $this->reviews->set_marking_active($review_id, true);
            }

            $audit = new AuditService();
            $audit->log(
                'review_marks_unlocked',
                'review',
                $review_id,
                null,
                json_encode(
                    [
                        'session_id' => $session_id,
                        'review_id' => $review_id,
                    ],
                    JSON_THROW_ON_ERROR
                ),
                $actor_user_id
            );
        }

        $review = $this->reviews->find_by_id($review_id) ?? $review;

        return [
            'coordinator_marks_locked' => false,
            'marking_active' => $this->reviews->is_marking_active($review_id),
        ];
    }

    /**
     * Whether a reviewer has finished marking a student for a review (freeze-ready).
     *
     * Absent students count complete without numeric scores; present students require a valid
     * score on every rubric criterion (draft or submitted mark rows).
     *
     * @param list<array<string, mixed>> $criteria
     */
    public function is_student_marking_complete(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria
    ): bool {
        return $this->student_marks_complete($session_id, $review_id, $student_id, $reviewer_user_id, $criteria);
    }

    /**
     * Coordinator progress status for a reviewer across all students on a panel.
     *
     * @param list<int> $student_ids
     * @param list<array<string, mixed>> $criteria
     */
    /**
     * Progress status for one assigned reviewer obligation on one student (review-mark grain).
     *
     * @param array{user_id: int, linked: bool} $reviewer
     * @param list<array<string, mixed>> $criteria
     */
    public function review_mark_status(
        int $session_id,
        int $review_id,
        int $student_id,
        array $reviewer,
        array $criteria
    ): string {
        $linked = (bool) ($reviewer['linked'] ?? false);
        $user_id = (int) ($reviewer['user_id'] ?? 0);

        if (!$linked || $user_id <= 0) {
            return 'not_started';
        }

        if ($this->is_student_marking_complete($session_id, $review_id, $student_id, $user_id, $criteria)) {
            return 'complete';
        }

        $mark_rows = $this->marks->list_for_student_review(
            $session_id,
            $review_id,
            $student_id,
            $user_id
        );
        if ($mark_rows !== []) {
            return 'in_progress';
        }

        return 'not_started';
    }

    public function reviewer_panel_marking_status(
        int $session_id,
        int $review_id,
        int $reviewer_user_id,
        array $student_ids,
        array $criteria,
        int $completed_count
    ): string {
        $total = count($student_ids);
        if ($total === 0) {
            return 'not_started';
        }

        if ($completed_count >= $total) {
            return 'complete';
        }

        $criteria_count = count($criteria);
        $has_activity = false;
        $all_frozen = $criteria_count > 0;

        foreach ($student_ids as $student_id) {
            if ($this->assignments->get_attendance_status($review_id, $student_id)
                === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
                $has_activity = true;
            }

            $mark_rows = $this->marks->list_for_student_review(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id
            );
            if ($mark_rows !== []) {
                $has_activity = true;
            }

            if ($criteria_count > 0 && !$this->marks->is_student_frozen_for_reviewer(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criteria_count
            )) {
                $all_frozen = false;
            }
        }

        if ($all_frozen && $has_activity) {
            return 'frozen';
        }

        if (!$has_activity && $completed_count === 0) {
            return 'not_started';
        }

        return 'in_progress';
    }

    /**
     * @param list<array<string, mixed>> $criteria
     */
    private function student_marks_complete(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria
    ): bool {
        return $this->list_missing_criteria(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            $criteria
        ) === [];
    }

    /**
     * @param list<array<string, mixed>> $criteria
     * @return list<array{id: int, label: string}>
     */
    private function list_missing_criteria(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria
    ): array {
        $guard = $this->validate_assignment($session_id, $review_id, $student_id, $reviewer_user_id);
        if ($guard instanceof \WP_Error) {
            return $this->all_criteria_refs($criteria);
        }

        if ($this->assignments->get_attendance_status($review_id, $student_id)
            === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            return [];
        }

        $rows_by_criterion = [];
        foreach ($this->marks->list_for_student_review($session_id, $review_id, $student_id, $reviewer_user_id) as $row) {
            $rows_by_criterion[(int) ($row['criterion_id'] ?? 0)] = $row;
        }

        $missing = [];
        foreach ($criteria as $criterion) {
            $criterion_id = (int) ($criterion['id'] ?? 0);
            $row = $rows_by_criterion[$criterion_id] ?? null;
            if ($row === null || $this->normalize_score($row['score'] ?? null) === null) {
                $missing[] = [
                    'id' => $criterion_id,
                    'label' => $this->criterion_label($criterion),
                ];
            }
        }

        return $missing;
    }

    /**
     * @param list<array<string, mixed>> $criteria
     * @return list<array{id: int, label: string}>
     */
    private function all_criteria_refs(array $criteria): array
    {
        $refs = [];
        foreach ($criteria as $criterion) {
            $refs[] = [
                'id' => (int) ($criterion['id'] ?? 0),
                'label' => $this->criterion_label($criterion),
            ];
        }

        return $refs;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function criterion_label(array $criterion): string
    {
        $label = trim((string) ($criterion['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $criterion_id = (int) ($criterion['id'] ?? 0);

        return sprintf(
            /* translators: %d: rubric criterion id */
            __('Criterion %d', 'scorva'),
            $criterion_id
        );
    }

    /**
     * @param list<array{student_id: int, student_name: string, student_reg_no: string, missing_criteria: list<array{id: int, label: string}>}> $incomplete_details
     */
    private function format_incomplete_marks_error_message(array $incomplete_details): string
    {
        $items = [];
        foreach ($incomplete_details as $detail) {
            $missing_labels = [];
            foreach ($detail['missing_criteria'] as $criterion) {
                $missing_labels[] = (string) ($criterion['label'] ?? '');
            }
            $missing_labels = array_values(array_filter(
                $missing_labels,
                static fn (string $label): bool => trim($label) !== ''
            ));
            if ($missing_labels === []) {
                continue;
            }

            $items[] = [
                'student_label' => $this->format_student_label_for_error(
                    (int) ($detail['student_id'] ?? 0),
                    (string) ($detail['student_name'] ?? ''),
                    (string) ($detail['student_reg_no'] ?? '')
                ),
                'missing_labels' => $missing_labels,
            ];
        }

        if ($items === []) {
            return __('Some students still need scores on every criterion before you can freeze.', 'scorva');
        }

        if (count($items) === 1) {
            $item = $items[0];
            if (count($item['missing_labels']) === 1) {
                return sprintf(
                    /* translators: 1: student name, 2: rubric criterion label */
                    __('%1$s still needs a score for %2$s.', 'scorva'),
                    $item['student_label'],
                    $item['missing_labels'][0]
                );
            }

            return sprintf(
                /* translators: 1: student name, 2: comma-separated criterion labels */
                __('%1$s still needs scores for: %2$s.', 'scorva'),
                $item['student_label'],
                $this->format_label_list($item['missing_labels'])
            );
        }

        $lines = [
            sprintf(
                /* translators: %d: number of students missing scores */
                _n(
                    '%d student still needs scores before you can freeze:',
                    '%d students still need scores before you can freeze:',
                    count($items),
                    'scorva'
                ),
                count($items)
            ),
        ];

        foreach ($items as $item) {
            $lines[] = sprintf(
                /* translators: 1: student name, 2: comma-separated criterion labels */
                __('• %1$s — missing: %2$s', 'scorva'),
                $item['student_label'],
                $this->format_label_list($item['missing_labels'])
            );
        }

        return implode("\n", $lines);
    }

    private function format_student_label_for_error(int $student_id, string $name, string $reg_no): string
    {
        $name = trim($name);
        $reg_no = trim($reg_no);
        if ($name === '') {
            $name = sprintf(
                /* translators: %d: student id */
                __('Student #%d', 'scorva'),
                $student_id
            );
        }

        if ($reg_no !== '') {
            return sprintf('%s (%s)', $name, $reg_no);
        }

        return $name;
    }

    /**
     * @param list<string> $labels
     */
    private function format_label_list(array $labels): string
    {
        $labels = array_values(array_filter($labels, static fn (string $label): bool => $label !== ''));
        if ($labels === []) {
            return '';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        if (count($labels) === 2) {
            return sprintf(
                /* translators: 1: first criterion label, 2: second criterion label */
                __('%1$s and %2$s', 'scorva'),
                $labels[0],
                $labels[1]
            );
        }

        $last = array_pop($labels);

        return sprintf(
            /* translators: 1: comma-separated criterion labels, 2: final criterion label */
            __('%1$s, and %2$s', 'scorva'),
            implode(', ', $labels),
            $last
        );
    }

    /**
     * @return list<int>
     */
    private function student_ids_for_panel(int $session_id, int $review_id, int $panel_id): array
    {
        $ids = [];
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

    private function validate_attendance_status(?string $attendance_status): string|\WP_Error
    {
        if ($attendance_status === null || trim($attendance_status) === '') {
            return new \WP_Error(
                'attendance_required',
                __('Attendance (present or absent) is required for each student.', 'scorva'),
                ['status' => 400]
            );
        }

        $attendance_status = strtolower(trim($attendance_status));
        if (!in_array(
            $attendance_status,
            [ReviewAssignmentRepository::ATTENDANCE_PRESENT, ReviewAssignmentRepository::ATTENDANCE_ABSENT],
            true
        )) {
            return new \WP_Error(
                'invalid_attendance',
                __('Attendance must be present or absent.', 'scorva'),
                ['status' => 400]
            );
        }

        return $attendance_status;
    }

    private function validate_attendance_consensus(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        string $attendance_status
    ): ?\WP_Error {
        $panel_id = $this->resolve_student_panel_id($session_id, $review_id, $student_id);
        if ($panel_id <= 0) {
            return new \WP_Error(
                'not_assigned',
                __('You are not assigned to mark this student.', 'scorva'),
                ['status' => 403]
            );
        }

        $status_by_reviewer = $this->panel_attendance_status_map(
            $session_id,
            $review_id,
            $student_id,
            $panel_id,
            $reviewer_user_id,
            $attendance_status
        );

        foreach ($status_by_reviewer as $peer_id => $peer_status) {
            if ($peer_id === $reviewer_user_id) {
                continue;
            }
            if ($peer_status !== $attendance_status) {
                return $this->attendance_conflict_error($panel_id, $status_by_reviewer);
            }
        }

        return null;
    }

    /**
     * Per-reviewer attendance for consensus: stored assertions plus peers with mark
     * activity who have not yet got an assertion row (e.g. saved before story 5-14).
     *
     * @return array<int, string>
     */
    private function panel_attendance_status_map(
        int $session_id,
        int $review_id,
        int $student_id,
        int $panel_id,
        int $reviewer_user_id,
        string $attempted_status
    ): array {
        $status_by_reviewer = [];
        foreach ($this->assignments->list_attendance_assertions_for_panel_student(
            $review_id,
            $student_id,
            $panel_id
        ) as $row) {
            $peer_id = (int) ($row['reviewer_user_id'] ?? 0);
            if ($peer_id <= 0) {
                continue;
            }
            $status_by_reviewer[$peer_id] = (string) ($row['attendance_status'] ?? '');
        }

        $canonical = $this->assignments->get_attendance_status($review_id, $student_id);
        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            $peer_id = (int) ($row['user_id'] ?? 0);
            if ($peer_id <= 0 || $peer_id === $reviewer_user_id) {
                continue;
            }
            if (isset($status_by_reviewer[$peer_id])) {
                continue;
            }
            if ($this->reviewer_has_mark_activity($session_id, $review_id, $student_id, $peer_id)) {
                $status_by_reviewer[$peer_id] = $canonical;
            }
        }

        $status_by_reviewer[$reviewer_user_id] = $attempted_status;

        return $status_by_reviewer;
    }

    private function reviewer_has_mark_activity(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id
    ): bool {
        return $this->marks->list_for_student_review(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id
        ) !== [];
    }

    /**
     * @param array<int, string> $status_by_reviewer
     */
    private function attendance_conflict_error(
        int $panel_id,
        array $status_by_reviewer
    ): \WP_Error {
        $panels = new PanelRepository();
        $conflicts = [];
        foreach ($status_by_reviewer as $user_id => $status) {
            $conflicts[] = [
                'reviewer_user_id' => $user_id,
                'reviewer_name' => $panels->display_name_for_user($panel_id, $user_id),
                'attendance_status' => $status,
            ];
        }

        usort(
            $conflicts,
            static function (array $a, array $b): int {
                return strcasecmp(
                    (string) ($a['reviewer_name'] ?? ''),
                    (string) ($b['reviewer_name'] ?? '')
                );
            }
        );

        return new \WP_Error(
            'attendance_conflict',
            __(
                'Attendance must match for all reviewers on this review. Resolve the disagreement before saving.',
                'scorva'
            ),
            [
                'status' => 400,
                'conflicts' => $conflicts,
            ]
        );
    }

    private function resolve_student_panel_id(int $session_id, int $review_id, int $student_id): int
    {
        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        if ($assignment !== null) {
            return (int) ($assignment['panel_id'] ?? 0);
        }

        $enrolment = $this->sessions->find_enrolment($session_id, $student_id);

        return (int) ($enrolment['panel_id'] ?? 0);
    }

    private function persist_attendance_status(
        int $session_id,
        int $review_id,
        int $student_id,
        string $attendance_status
    ): ?\WP_Error {
        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        if ($assignment === null) {
            $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
            if ($panel_id <= 0) {
                return new \WP_Error(
                    'not_assigned',
                    __('You are not assigned to mark this student.', 'scorva'),
                    ['status' => 403]
                );
            }
            $this->assignments->set_student_panel($review_id, $student_id, $panel_id);
        }

        $this->assignments->set_attendance_status($review_id, $student_id, $attendance_status);

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $criteria_by_id
     * @return array{marks: list<array<string, mixed>>, attendance_status: string}
     */
    private function save_absent_marks(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria_by_id,
        string $status
    ): array {
        $saved = [];
        foreach (array_keys($criteria_by_id) as $criterion_id) {
            $mark_id = $this->marks->upsert(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criterion_id,
                null,
                $status
            );

            $saved[] = [
                'id' => $mark_id,
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => $student_id,
                'reviewer_user_id' => $reviewer_user_id,
                'criterion_id' => $criterion_id,
                'score' => null,
                'status' => $status,
                'flagged' => false,
            ];
        }

        return [
            'marks' => $saved,
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
        ];
    }

    private function null_panel_reviewer_marks_for_absent(
        int $session_id,
        int $review_id,
        int $student_id,
        int $panel_id
    ): void {
        $criteria_by_id = [];
        foreach ($this->reviews->list_criteria($review_id) as $row) {
            $criterion_id = (int) ($row['id'] ?? 0);
            if ($criterion_id > 0) {
                $criteria_by_id[$criterion_id] = $row;
            }
        }

        if ($criteria_by_id === []) {
            return;
        }

        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $reviewer_row) {
            $reviewer_user_id = (int) ($reviewer_row['user_id'] ?? 0);
            if ($reviewer_user_id <= 0) {
                continue;
            }

            $existing_marks = $this->marks->list_for_student_review(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id
            );
            $status_by_criterion = [];
            foreach ($existing_marks as $mark) {
                $criterion_id = (int) ($mark['criterion_id'] ?? 0);
                if ($criterion_id > 0) {
                    $status_by_criterion[$criterion_id] = (string) ($mark['status'] ?? MarkRepository::STATUS_DRAFT);
                }
            }
            $default_status = MarkRepository::STATUS_DRAFT;
            if ($existing_marks !== []) {
                $default_status = (string) ($existing_marks[0]['status'] ?? MarkRepository::STATUS_DRAFT);
            }

            foreach (array_keys($criteria_by_id) as $criterion_id) {
                $status = $status_by_criterion[$criterion_id] ?? $default_status;
                $this->marks->upsert(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewer_user_id,
                    $criterion_id,
                    null,
                    $status
                );
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $criteria
     */
    private function ensure_absent_marks_draft(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        array $criteria
    ): void {
        foreach ($criteria as $criterion) {
            $criterion_id = (int) ($criterion['id'] ?? 0);
            if ($criterion_id <= 0) {
                continue;
            }
            $this->marks->upsert(
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id,
                $criterion_id,
                null,
                MarkRepository::STATUS_DRAFT
            );
        }
    }
}

