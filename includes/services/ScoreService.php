<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;

/**
 * Three-level scoring (design spec §6):
 * Level 1 — reviewer total = sum of submitted criterion marks (raw marks)
 * Level 2 — review score = weighted average of reviewer totals (reviewer weight, default 1)
 * Level 3 — combined score = weighted average of review scores (review weight, default 1)
 */
final class ScoreService
{
    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private PanelRepository $panels;

    private ReviewAssignmentRepository $assignments;

    private MarkRepository $marks;

    private MarkService $mark_service;

    public function __construct(
        ?SessionRepository $sessions = null,
        ?ReviewRepository $reviews = null,
        ?PanelRepository $panels = null,
        ?ReviewAssignmentRepository $assignments = null,
        ?MarkRepository $marks = null,
        ?MarkService $mark_service = null
    ) {
        $this->sessions = $sessions ?? new SessionRepository();
        $this->reviews = $reviews ?? new ReviewRepository();
        $this->panels = $panels ?? new PanelRepository();
        $this->assignments = $assignments ?? new ReviewAssignmentRepository();
        $this->marks = $marks ?? new MarkRepository();
        $this->mark_service = $mark_service ?? new MarkService(
            $this->sessions,
            $this->reviews,
            $this->assignments,
            $this->marks
        );
    }

    /**
     * Level 1: sum of submitted criterion marks for one reviewer (raw marks, not %).
     */
    public function calculate_reviewer_total(
        int $session_id,
        int $student_id,
        int $review_id,
        int $reviewer_user_id,
        bool $submitted_only = true
    ): float {
        $criteria = $this->reviews->list_criteria($review_id);
        $criteria_by_id = [];
        foreach ($criteria as $row) {
            $criteria_by_id[(int) $row['id']] = true;
        }

        if ($criteria_by_id === []) {
            return 0.0;
        }

        $marks = $this->marks->list_for_student_review($session_id, $review_id, $student_id, $reviewer_user_id);
        $sum = 0.0;

        foreach ($marks as $mark) {
            if ($submitted_only && (string) ($mark['status'] ?? '') !== MarkRepository::STATUS_SUBMITTED) {
                continue;
            }
            if ($mark['score'] === null) {
                continue;
            }

            $criterion_id = (int) ($mark['criterion_id'] ?? 0);
            if (!isset($criteria_by_id[$criterion_id])) {
                continue;
            }

            $sum += (float) $mark['score'];
        }

        return round($sum, 2);
    }

    /**
     * Level 2: weighted average of reviewer totals for a review.
     *
     * @return array{
     *     review_score: float,
     *     reviewers: list<array{reviewer_user_id: int, reviewer_total: float, weight: float}>
     * }
     */
    public function calculate_review_score(
        int $session_id,
        int $student_id,
        int $review_id
    ): array {
        $reviewer_ids = $this->marks->list_reviewer_user_ids_for_student_review(
            $session_id,
            $student_id,
            $review_id
        );

        if ($reviewer_ids === []) {
            return ['review_score' => 0.0, 'reviewers' => []];
        }

        $assignment = $this->assignments->get_student_panel($review_id, $student_id);
        $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
        $panel_id = $assignment !== null
            ? (int) ($assignment['panel_id'] ?? 0)
            : ($enrolment !== null ? (int) ($enrolment['panel_id'] ?? 0) : 0);

        $panel_weights = [];
        if ($panel_id > 0) {
            foreach ($this->assignments->list_panel_reviewers($review_id) as $reviewer) {
                if ((int) ($reviewer['panel_id'] ?? 0) !== $panel_id) {
                    continue;
                }
                $uid = (int) ($reviewer['user_id'] ?? 0);
                if ($uid > 0) {
                    $panel_weights[$uid] = (float) ($reviewer['weight'] ?? 1);
                }
            }
        }

        $reviewer_weight_rows = $this->reviews->list_reviewer_weights($review_id);
        $override_weights = [];
        foreach ($reviewer_weight_rows as $row) {
            $override_weights[(int) ($row['reviewer_user_id'] ?? 0)] = (float) ($row['weight'] ?? 1);
        }

        $reviewers = [];
        $weighted_sum = 0.0;
        $weight_sum = 0.0;

        foreach ($reviewer_ids as $reviewer_user_id) {
            $total = $this->calculate_reviewer_total($session_id, $student_id, $review_id, $reviewer_user_id);
            $weight = $override_weights[$reviewer_user_id] ?? $panel_weights[$reviewer_user_id] ?? 1.0;
            if ($weight <= 0) {
                $weight = 1.0;
            }

            $reviewers[] = [
                'reviewer_user_id' => $reviewer_user_id,
                'reviewer_total' => $total,
                'weight' => $weight,
            ];
            $weighted_sum += $total * $weight;
            $weight_sum += $weight;
        }

        $review_score = $weight_sum > 0 ? round($weighted_sum / $weight_sum, 2) : 0.0;

        return [
            'review_score' => $review_score,
            'reviewers' => $reviewers,
        ];
    }

    /**
     * Level 3: weighted average of review scores across confirmed reviews.
     *
     * @return array{
     *     combined_score: float,
     *     reviews: list<array{review_id: int, label: string, review_score: float, weight: float}>
     * }
     */
    public function calculate_combined_score(int $session_id, int $student_id): array
    {
        $reviews = [];
        $weighted_sum = 0.0;
        $weight_sum = 0.0;

        foreach ($this->reviews->list_for_session($session_id) as $review) {
            if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
                continue;
            }

            $review_id = (int) ($review['id'] ?? 0);
            $aggregate = $this->calculate_review_score($session_id, $student_id, $review_id);
            $review_weight = $this->reviews->get_review_weight($session_id, $review_id);
            if ($review_weight <= 0) {
                $review_weight = 1.0;
            }

            $reviews[] = [
                'review_id' => $review_id,
                'label' => (string) ($review['label'] ?? ''),
                'review_score' => $aggregate['review_score'],
                'weight' => $review_weight,
            ];

            $weighted_sum += $aggregate['review_score'] * $review_weight;
            $weight_sum += $review_weight;
        }

        $combined = $weight_sum > 0 ? round($weighted_sum / $weight_sum, 2) : 0.0;

        return [
            'combined_score' => $combined,
            'reviews' => $reviews,
        ];
    }

    /**
     * Full breakdown for REST/UI.
     *
     * @return array<string, mixed>
     */
    public function get_student_breakdown(int $session_id, int $student_id): array
    {
        $combined = $this->calculate_combined_score($session_id, $student_id);
        $reviews_detail = [];

        foreach ($combined['reviews'] as $review_row) {
            $review_id = (int) $review_row['review_id'];
            $aggregate = $this->calculate_review_score($session_id, $student_id, $review_id);
            $reviews_detail[] = [
                'review_id' => $review_id,
                'label' => $review_row['label'],
                'review_score' => $aggregate['review_score'],
                'weight' => $review_row['weight'],
                'reviewers' => $aggregate['reviewers'],
                'attendance_status' => $this->assignments->get_attendance_status($review_id, $student_id),
            ];
        }

        return [
            'session_id' => $session_id,
            'student_id' => $student_id,
            'combined_score' => $combined['combined_score'],
            'reviews' => $reviews_detail,
        ];
    }

    /**
     * Marking progress for a session at student grain, grouped by confirmed review.
     *
     * Each panel × reviewer row counts students fully marked (freeze-ready) for that review.
     * Review summary counts students where every panel reviewer has finished that student.
     *
     * @return list<array{
     *     review_id: int,
     *     review_label: string,
     *     summary: array{
     *         students_completed: int,
     *         students_total: int,
     *         marks_completed: int,
     *         marks_in_progress: int,
     *         marks_not_started: int,
     *         marks_total: int,
     *         percent: float
     *     },
     *     panels: list<array{
     *         panel_id: int,
     *         panel_name: string,
     *         students_total: int,
     *         summary: array{
     *             marks_completed: int,
     *             marks_in_progress: int,
     *             marks_not_started: int,
     *             marks_total: int,
     *             percent: float,
     *             students_total: int
     *         },
     *         rows: list<array<string, mixed>>
     *     }>,
     *     rows: list<array<string, mixed>>
     * }>
     */
    public function calculate_session_progress(int $session_id): array
    {
        $confirmed_reviews = array_values(array_filter(
            $this->reviews->list_for_session($session_id),
            static fn (array $review): bool => (string) ($review['status'] ?? '') === ReviewRepository::STATUS_CONFIRMED
        ));

        if ($confirmed_reviews === []) {
            return [];
        }

        $panel_names = [];
        foreach ($this->panels->list_by_session($session_id) as $panel) {
            $panel_names[(int) ($panel['id'] ?? 0)] = (string) ($panel['name'] ?? '');
        }

        $reviews_out = [];

        foreach ($confirmed_reviews as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            $criteria = $this->reviews->list_criteria($review_id);
            if ($criteria === []) {
                continue;
            }

            $students_by_panel = $this->students_by_panel_for_review($session_id, $review_id);
            $panel_ids = $this->panel_ids_for_progress($session_id, $students_by_panel, $review_id);

            $panels_out = [];
            $rows = [];

            foreach ($panel_ids as $panel_id) {
                $student_ids = $students_by_panel[$panel_id] ?? [];
                $assigned_reviewers = $this->assigned_reviewers_for_panel($review_id, $panel_id);
                if ($assigned_reviewers === [] && $student_ids === []) {
                    continue;
                }

                $panel_rows = [];
                foreach ($assigned_reviewers as $reviewer) {
                    $panel_rows[] = $this->build_progress_row(
                        $session_id,
                        $review_id,
                        $panel_id,
                        $panel_names[$panel_id] ?? '',
                        $reviewer,
                        $student_ids,
                        $criteria
                    );
                }

                usort(
                    $panel_rows,
                    static fn (array $a, array $b): int => strcmp(
                        (string) ($a['reviewer_name'] ?? ''),
                        (string) ($b['reviewer_name'] ?? '')
                    )
                );

                $panel_mark_summary = $this->compute_mark_summary(
                    $session_id,
                    $review_id,
                    $student_ids,
                    $assigned_reviewers,
                    $criteria
                );

                $panels_out[] = [
                    'panel_id' => $panel_id,
                    'panel_name' => $panel_names[$panel_id] ?? '',
                    'students_total' => count($student_ids),
                    'summary' => $panel_mark_summary,
                    'rows' => $panel_rows,
                ];
                foreach ($panel_rows as $panel_row) {
                    $rows[] = $panel_row;
                }
            }

            usort(
                $panels_out,
                static fn (array $a, array $b): int => strcmp(
                    (string) ($a['panel_name'] ?? ''),
                    (string) ($b['panel_name'] ?? '')
                )
            );

            $students_with_panel = $this->assigned_students_with_panel($session_id, $review_id);
            $students_total = count($students_with_panel);
            $students_completed = 0;
            foreach ($students_with_panel as $student_id => $panel_id) {
                $assigned_reviewers = $this->assigned_reviewers_for_panel($review_id, $panel_id);
                $marking_reviewer_ids = [];
                foreach ($assigned_reviewers as $reviewer) {
                    if (!($reviewer['linked'] ?? false)) {
                        continue;
                    }
                    $user_id = (int) ($reviewer['user_id'] ?? 0);
                    if ($user_id > 0) {
                        $marking_reviewer_ids[] = $user_id;
                    }
                }
                if ($marking_reviewer_ids === []) {
                    continue;
                }

                $student_complete = true;
                foreach ($marking_reviewer_ids as $user_id) {
                    if (!$this->mark_service->is_student_marking_complete(
                        $session_id,
                        $review_id,
                        $student_id,
                        $user_id,
                        $criteria
                    )) {
                        $student_complete = false;
                        break;
                    }
                }

                if ($student_complete) {
                    ++$students_completed;
                }
            }

            $marks_completed = 0;
            $marks_in_progress = 0;
            $marks_not_started = 0;
            $marks_total = 0;

            foreach ($panels_out as $panel) {
                $panel_summary = $panel['summary'] ?? [];
                $marks_completed += (int) ($panel_summary['marks_completed'] ?? 0);
                $marks_in_progress += (int) ($panel_summary['marks_in_progress'] ?? 0);
                $marks_not_started += (int) ($panel_summary['marks_not_started'] ?? 0);
                $marks_total += (int) ($panel_summary['marks_total'] ?? 0);
            }

            $summary_percent = $marks_total > 0
                ? round(($marks_completed / $marks_total) * 100, 1)
                : 0.0;

            $reviews_out[] = [
                'review_id' => $review_id,
                'review_label' => (string) ($review['label'] ?? ''),
                'summary' => [
                    'students_completed' => $students_completed,
                    'students_total' => $students_total,
                    'marks_completed' => $marks_completed,
                    'marks_in_progress' => $marks_in_progress,
                    'marks_not_started' => $marks_not_started,
                    'marks_total' => $marks_total,
                    'percent' => $summary_percent,
                ],
                'panels' => $panels_out,
                'rows' => $rows,
            ];
        }

        return $reviews_out;
    }

    /**
     * @return array<int, list<int>>
     */
    private function students_by_panel_for_review(int $session_id, int $review_id): array
    {
        $by_panel = [];
        $assignments = $this->assignments->list_student_panels($review_id);

        if ($assignments !== []) {
            foreach ($assignments as $row) {
                $student_id = (int) ($row['student_id'] ?? 0);
                $panel_id = (int) ($row['panel_id'] ?? 0);
                if ($student_id <= 0 || $panel_id <= 0) {
                    continue;
                }
                $by_panel[$panel_id] ??= [];
                if (!in_array($student_id, $by_panel[$panel_id], true)) {
                    $by_panel[$panel_id][] = $student_id;
                }
            }

            return $by_panel;
        }

        foreach ($this->sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
            if ($student_id <= 0 || $panel_id <= 0) {
                continue;
            }
            $by_panel[$panel_id] ??= [];
            if (!in_array($student_id, $by_panel[$panel_id], true)) {
                $by_panel[$panel_id][] = $student_id;
            }
        }

        return $by_panel;
    }

    /**
     * @return array<int, int> student_id => panel_id
     */
    private function assigned_students_with_panel(int $session_id, int $review_id): array
    {
        $students_with_panel = [];
        foreach ($this->assignments->list_student_panels($review_id) as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($student_id > 0 && $panel_id > 0) {
                $students_with_panel[$student_id] = $panel_id;
            }
        }

        if ($students_with_panel !== []) {
            return $students_with_panel;
        }

        foreach ($this->sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
            if ($student_id > 0 && $panel_id > 0) {
                $students_with_panel[$student_id] = $panel_id;
            }
        }

        return $students_with_panel;
    }

    /**
     * @param array<int, list<int>> $students_by_panel
     *
     * @return list<int>
     */
    private function panel_ids_for_progress(int $session_id, array $students_by_panel, int $review_id): array
    {
        $panel_ids = [];

        foreach ($this->panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id > 0) {
                $panel_ids[$panel_id] = true;
            }
        }

        foreach (array_keys($students_by_panel) as $panel_id) {
            if ($panel_id > 0) {
                $panel_ids[$panel_id] = true;
            }
        }

        foreach ($this->assignments->list_panel_reviewers($review_id) as $reviewer_row) {
            $panel_id = (int) ($reviewer_row['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $panel_ids[$panel_id] = true;
            }
        }

        $ids = array_keys($panel_ids);
        sort($ids);

        return $ids;
    }

    /**
     * All reviewers on a panel for this review.
     *
     * Session panel roster (`pr_panel_reviewers`) is authoritative — includes reviewers
     * without a linked WordPress user. Per-review rows only add provisioned users missing
     * from the roster (legacy data).
     *
     * @return list<array{user_id: int, name: string, email: string, panel_reviewer_id: int, linked: bool}>
     */
    private function assigned_reviewers_for_panel(int $review_id, int $panel_id): array
    {
        $by_panel_reviewer_id = [];

        foreach ($this->panels->list_reviewers($panel_id) as $session_reviewer) {
            $panel_reviewer_id = (int) ($session_reviewer['id'] ?? 0);
            if ($panel_reviewer_id <= 0) {
                continue;
            }

            $user_id = $this->panel_reviewer_user_id($session_reviewer);
            $by_panel_reviewer_id[$panel_reviewer_id] = [
                'user_id' => $user_id,
                'name' => trim((string) ($session_reviewer['name'] ?? '')),
                'email' => trim((string) ($session_reviewer['email'] ?? '')),
                'panel_reviewer_id' => $panel_reviewer_id,
                'linked' => $user_id > 0,
            ];
        }

        foreach ($this->assignments->list_panel_reviewers($review_id) as $reviewer_row) {
            if ((int) ($reviewer_row['panel_id'] ?? 0) !== $panel_id) {
                continue;
            }

            $user_id = (int) ($reviewer_row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $already_listed = false;
            foreach ($by_panel_reviewer_id as $entry) {
                if ((int) ($entry['user_id'] ?? 0) === $user_id) {
                    $already_listed = true;
                    break;
                }
            }

            if ($already_listed) {
                continue;
            }

            $by_panel_reviewer_id['u' . $user_id] = [
                'user_id' => $user_id,
                'name' => $this->reviewer_display_name_for_user($panel_id, $user_id),
                'email' => '',
                'panel_reviewer_id' => 0,
                'linked' => true,
            ];
        }

        $reviewers = array_values($by_panel_reviewer_id);
        usort(
            $reviewers,
            static fn (array $a, array $b): int => strcasecmp(
                (string) ($a['name'] ?? ''),
                (string) ($b['name'] ?? '')
            )
        );

        return $reviewers;
    }

    /**
     * @param array<string, mixed> $session_reviewer
     */
    private function panel_reviewer_user_id(array $session_reviewer): int
    {
        if (!isset($session_reviewer['user_id']) || $session_reviewer['user_id'] === null || $session_reviewer['user_id'] === '') {
            return 0;
        }

        return (int) $session_reviewer['user_id'];
    }

    private function reviewer_display_name_for_user(int $panel_id, int $user_id): string
    {
        foreach ($this->panels->list_reviewers($panel_id) as $session_reviewer) {
            if ($this->panel_reviewer_user_id($session_reviewer) === $user_id) {
                $name = trim((string) ($session_reviewer['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        if (function_exists('get_userdata')) {
            $user = get_userdata($user_id);
            if ($user !== false) {
                return (string) ($user->display_name ?? '');
            }
        }

        return '';
    }

    /**
     * @param list<int> $student_ids
     * @param list<array{user_id: int, name: string, email: string, panel_reviewer_id: int, linked: bool}> $assigned_reviewers
     * @param list<array<string, mixed>> $criteria
     *
     * @return array{
     *     marks_completed: int,
     *     marks_in_progress: int,
     *     marks_not_started: int,
     *     marks_total: int,
     *     percent: float,
     *     students_total: int
     * }
     */
    private function compute_mark_summary(
        int $session_id,
        int $review_id,
        array $student_ids,
        array $assigned_reviewers,
        array $criteria
    ): array {
        $marks_completed = 0;
        $marks_in_progress = 0;
        $marks_not_started = 0;

        foreach ($student_ids as $student_id) {
            foreach ($assigned_reviewers as $reviewer) {
                $status = $this->mark_service->review_mark_status(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewer,
                    $criteria
                );

                if ($status === 'complete') {
                    ++$marks_completed;
                } elseif ($status === 'in_progress') {
                    ++$marks_in_progress;
                } else {
                    ++$marks_not_started;
                }
            }
        }

        $marks_total = $marks_completed + $marks_in_progress + $marks_not_started;
        $percent = $marks_total > 0
            ? round(($marks_completed / $marks_total) * 100, 1)
            : 0.0;

        return [
            'marks_completed' => $marks_completed,
            'marks_in_progress' => $marks_in_progress,
            'marks_not_started' => $marks_not_started,
            'marks_total' => $marks_total,
            'percent' => $percent,
            'students_total' => count($student_ids),
        ];
    }

    /**
     * @param array{user_id: int, name: string, email: string, panel_reviewer_id: int, linked: bool} $reviewer
     * @param list<int> $student_ids
     * @param list<array<string, mixed>> $criteria
     *
     * @return array<string, mixed>
     */
    private function build_progress_row(
        int $session_id,
        int $review_id,
        int $panel_id,
        string $panel_name,
        array $reviewer,
        array $student_ids,
        array $criteria
    ): array {
        $user_id = (int) ($reviewer['user_id'] ?? 0);
        $linked = (bool) ($reviewer['linked'] ?? $user_id > 0);
        $reviewer_name = trim((string) ($reviewer['name'] ?? ''));
        if ($reviewer_name === '' && trim((string) ($reviewer['email'] ?? '')) !== '') {
            $reviewer_name = trim((string) $reviewer['email']);
        }

        $panel_reviewer_id = (int) ($reviewer['panel_reviewer_id'] ?? 0);

        $completed = 0;
        if ($linked && $user_id > 0) {
            foreach ($student_ids as $student_id) {
                if ($this->mark_service->is_student_marking_complete(
                    $session_id,
                    $review_id,
                    $student_id,
                    $user_id,
                    $criteria
                )) {
                    ++$completed;
                }
            }
        }

        $total = count($student_ids);
        $percent = $total > 0
            ? round(($completed / $total) * 100, 1)
            : 0.0;

        $status = 'not_started';
        if ($linked && $user_id > 0) {
            $status = $this->mark_service->reviewer_panel_marking_status(
                $session_id,
                $review_id,
                $user_id,
                $student_ids,
                $criteria,
                $completed
            );
        }

        return [
            'panel_id' => $panel_id,
            'panel_name' => $panel_name,
            'panel_reviewer_id' => $panel_reviewer_id,
            'reviewer_user_id' => $user_id > 0 ? $user_id : null,
            'reviewer_name' => $reviewer_name,
            'reviewer_email' => trim((string) ($reviewer['email'] ?? '')),
            'linked' => $linked,
            'completed' => $completed,
            'total' => $total,
            'percent' => $percent,
            'status' => $status,
        ];
    }

    /**
     * Back-compat for export stub callers.
     *
     * @return array{reviewer_total: float, review_score: float, combined_score: float}
     */
    public function calculate_student_review_scores(
        int $session_id,
        int $student_id,
        int $review_id,
        int $reviewer_user_id
    ): array {
        $reviewer_total = $this->calculate_reviewer_total($session_id, $student_id, $review_id, $reviewer_user_id);
        $review = $this->calculate_review_score($session_id, $student_id, $review_id);
        $combined = $this->calculate_combined_score($session_id, $student_id);

        return [
            'reviewer_total' => $reviewer_total,
            'review_score' => $review['review_score'],
            'combined_score' => $combined['combined_score'],
        ];
    }

    /**
     * @return array{review_score: float, combined_score: float}
     */
    public function calculate_student_review_aggregate(
        int $session_id,
        int $student_id,
        int $review_id
    ): array {
        $review = $this->calculate_review_score($session_id, $student_id, $review_id);
        $combined = $this->calculate_combined_score($session_id, $student_id);

        return [
            'review_score' => $review['review_score'],
            'combined_score' => $combined['combined_score'],
        ];
    }
}
