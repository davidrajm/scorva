<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class StudentEnrolmentService
{
    public const CONFIRM_WITH_SCORES_PHRASE = 'Confirm';

    private SessionRepository $sessions;

    private StudentRepository $students;

    private MarkRepository $marks;

    public function __construct(?object $wpdb = null)
    {
        $this->sessions = new SessionRepository($wpdb);
        $this->students = new StudentRepository($wpdb);
        $this->marks = new MarkRepository($wpdb);
    }

    /**
     * @return array{removed: true, registry_deleted: bool}|\WP_Error
     */
    public function remove_from_project(
        int $session_id,
        int $student_id,
        bool $allow_with_scores = false
    ): array|\WP_Error {
        if ($this->sessions->find_enrolment($session_id, $student_id) === null) {
            return new \WP_Error(
                'pr_enrolment_not_found',
                __('Student is not enrolled in this project.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $has_scores = $this->marks->student_has_numeric_scores_in_session($session_id, $student_id);

        if ($has_scores && !$allow_with_scores) {
            return new \WP_Error(
                'pr_student_has_scores',
                __('Cannot remove this student because marking has started in one or more review rounds.', 'project-reviews'),
                ['status' => 409]
            );
        }

        if ($has_scores && $allow_with_scores) {
            $this->marks->delete_all_for_student_in_session($session_id, $student_id);
        }

        if (!$this->sessions->remove_enrolment($session_id, $student_id)) {
            return new \WP_Error(
                'pr_enrolment_not_found',
                __('Student is not enrolled in this project.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $registry_deleted = false;
        if ($this->students->count_enrolments($student_id) === 0) {
            $registry_deleted = $this->students->delete($student_id);
        }

        return [
            'removed' => true,
            'registry_deleted' => $registry_deleted,
        ];
    }

    /**
     * @return array{
     *     removed: int,
     *     registry_deleted: int,
     *     skipped_has_scores: int
     * }|\WP_Error
     */
    public function remove_all_from_project(int $session_id, bool $allow_with_scores = false): array|\WP_Error
    {
        $enrolled = $this->sessions->list_enrolled($session_id);

        if ($enrolled === []) {
            return [
                'removed' => 0,
                'registry_deleted' => 0,
                'skipped_has_scores' => 0,
            ];
        }

        $any_has_scores = false;
        foreach ($enrolled as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }
            if ($this->marks->student_has_numeric_scores_in_session($session_id, $student_id)) {
                $any_has_scores = true;
                break;
            }
        }

        if ($any_has_scores && !$allow_with_scores) {
            return new \WP_Error(
                'pr_remove_students_confirmation_required',
                sprintf(
                    /* translators: %s is the exact confirmation word the user must type */
                    __('Type %s to remove all students including those with entered scores.', 'project-reviews'),
                    self::CONFIRM_WITH_SCORES_PHRASE
                ),
                ['status' => 400]
            );
        }

        $removed = 0;
        $registry_deleted = 0;

        foreach ($enrolled as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $result = $this->remove_from_project($session_id, $student_id, $allow_with_scores);
            if ($result instanceof \WP_Error) {
                return $result;
            }

            $removed++;
            if ($result['registry_deleted'] ?? false) {
                $registry_deleted++;
            }
        }

        return [
            'removed' => $removed,
            'registry_deleted' => $registry_deleted,
            'skipped_has_scores' => 0,
        ];
    }
}
