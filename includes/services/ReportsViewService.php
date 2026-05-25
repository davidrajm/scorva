<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class ReportsViewService
{
    /** Default marks matrix layout (coordinator Reports tab and backup bundle). */
    public const DEFAULT_MARKS_MATRIX_LAYOUT = 'rubric';

    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private ReviewAssignmentRepository $assignments;

    private StudentRepository $students;

    private MarkRepository $marks;

    private ScoreService $scores;

    private PanelRepository $panels;

    public function __construct(
        ?SessionRepository $sessions = null,
        ?ReviewRepository $reviews = null,
        ?ReviewAssignmentRepository $assignments = null,
        ?StudentRepository $students = null,
        ?MarkRepository $marks = null,
        ?ScoreService $scores = null,
        ?PanelRepository $panels = null
    ) {
        $this->sessions = $sessions ?? new SessionRepository();
        $this->reviews = $reviews ?? new ReviewRepository();
        $this->assignments = $assignments ?? new ReviewAssignmentRepository();
        $this->students = $students ?? new StudentRepository();
        $this->marks = $marks ?? new MarkRepository();
        $this->scores = $scores ?? new ScoreService();
        $this->panels = $panels ?? new PanelRepository();
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function marks_grid(int $session_id, int $review_id): array|\WP_Error
    {
        $review = $this->require_confirmed_review($session_id, $review_id);
        if ($review instanceof \WP_Error) {
            return $review;
        }

        $criteria_payload = [];
        foreach ($this->reviews->list_criteria($review_id) as $row) {
            $criteria_payload[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'max_marks' => (float) ($row['max_marks'] ?? 0),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        $marks_by_student_criterion = [];
        foreach ($this->marks->list_for_review($review_id) as $mark) {
            if ((int) ($mark['session_id'] ?? 0) !== $session_id) {
                continue;
            }
            $student_id = (int) ($mark['student_id'] ?? 0);
            $criterion_id = (int) ($mark['criterion_id'] ?? 0);
            $reviewer_user_id = (int) ($mark['reviewer_user_id'] ?? 0);
            if ($student_id <= 0 || $criterion_id <= 0 || $reviewer_user_id <= 0) {
                continue;
            }

            $marks_by_student_criterion[$student_id][$criterion_id][] = [
                'id' => (int) ($mark['id'] ?? 0),
                'reviewer_user_id' => $reviewer_user_id,
                'reviewer_name' => $this->reviewer_display_name($review_id, $reviewer_user_id),
                'score' => $mark['score'] !== null ? (float) $mark['score'] : null,
                'status' => (string) ($mark['status'] ?? MarkRepository::STATUS_DRAFT),
                'flagged' => (bool) (int) ($mark['flagged'] ?? 0),
                'coordinator_overridden' => (bool) (int) ($mark['coordinator_overridden'] ?? 0),
                'overridden_from_score' => isset($mark['overridden_from_score']) && $mark['overridden_from_score'] !== null && $mark['overridden_from_score'] !== ''
                    ? (float) $mark['overridden_from_score']
                    : null,
            ];
        }

        $coordinator_locked = $this->reviews->is_coordinator_marks_locked($review_id);
        $lock_readiness = (new MarkService(
            $this->sessions,
            $this->reviews,
            $this->assignments,
            $this->marks
        ))->review_lock_readiness($review_id);
        $criteria_count = count($criteria_payload);
        $max_panel_reviewer_slots = $this->compute_max_panel_reviewer_slots($session_id, $review_id);
        $panel_id_by_student = $this->panel_id_map_for_review($session_id, $review_id);

        $students_payload = [];
        foreach ($this->list_enrolled_students($session_id) as $student) {
            $student_id = (int) ($student['id'] ?? 0);
            $panel_id = $panel_id_by_student[$student_id] ?? null;
            $panel_reviewers = $this->build_panel_reviewers_payload($review_id, $panel_id);
            $marks_map = [];
            foreach ($criteria_payload as $criterion) {
                $criterion_id = (int) ($criterion['id'] ?? 0);
                $marks_map[(string) $criterion_id] = $marks_by_student_criterion[$student_id][$criterion_id] ?? [];
            }

            $panel_context = $this->panel_context_for_student($review_id, $panel_id, $panel_reviewers);

            $students_payload[] = array_merge([
                'student_id' => $student_id,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'guide_emp_id' => (string) ($student['guide_emp_id'] ?? ''),
                'guide_name' => (string) ($student['guide_name'] ?? ''),
                'project_title' => $this->assignments->resolve_project_title($session_id, $review_id, $student_id),
                'panel_id' => $panel_id,
                'panel_reviewers' => $panel_reviewers,
                'attendance_status' => $this->assignments->get_attendance_status($review_id, $student_id),
                'mark_status' => $this->student_report_mark_status(
                    $session_id,
                    $review_id,
                    $student_id,
                    $panel_reviewers,
                    $criteria_payload,
                    $coordinator_locked
                ),
                'marks' => $marks_map,
            ], $panel_context);
        }

        return [
            'review_id' => $review_id,
            'criteria' => $criteria_payload,
            'students' => $students_payload,
            'max_panel_reviewer_slots' => $max_panel_reviewer_slots,
            'coordinator_marks_locked' => $coordinator_locked,
            'review_lock_ready' => !$coordinator_locked && $lock_readiness['review_lock_ready'],
            'unfrozen_panels' => $lock_readiness['unfrozen_panels'],
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function scores_matrix(int $session_id, int $review_id): array|\WP_Error
    {
        $review = $this->require_confirmed_review($session_id, $review_id);
        if ($review instanceof \WP_Error) {
            return $review;
        }

        $criteria = $this->reviews->list_criteria($review_id);
        $criteria_count = count($criteria);
        $max_panel_reviewer_slots = $this->compute_max_panel_reviewer_slots($session_id, $review_id);
        $panel_id_by_student = $this->panel_id_map_for_review($session_id, $review_id);
        $reviewers = $this->list_reviewers_for_review($review_id);
        $coordinator_locked = $this->reviews->is_coordinator_marks_locked($review_id);

        $students_payload = [];
        foreach ($this->list_enrolled_students($session_id) as $student) {
            $student_id = (int) ($student['id'] ?? 0);
            $panel_id = $panel_id_by_student[$student_id] ?? null;
            $panel_reviewers = $this->build_panel_reviewers_payload($review_id, $panel_id);
            $panel_reviewers_with_totals = [];
            $reviewer_totals = [];

            foreach ($panel_reviewers as $reviewer) {
                $user_id = (int) ($reviewer['user_id'] ?? 0);
                $total_cell = $user_id > 0
                    ? $this->reviewer_total_cell_for_panel_report(
                        $session_id,
                        $review_id,
                        $student_id,
                        $user_id,
                        $criteria_count
                    )
                    : null;
                $panel_reviewers_with_totals[] = array_merge($reviewer, [
                    'total' => $total_cell,
                ]);
                if ($user_id > 0) {
                    $reviewer_totals[(string) $user_id] = $total_cell;
                }
            }

            $aggregate = $this->scores->calculate_review_score($session_id, $student_id, $review_id);
            $panel_context = $this->panel_context_for_student($review_id, $panel_id, $panel_reviewers);

            $students_payload[] = array_merge([
                'student_id' => $student_id,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'guide_emp_id' => (string) ($student['guide_emp_id'] ?? ''),
                'guide_name' => (string) ($student['guide_name'] ?? ''),
                'project_title' => $this->assignments->resolve_project_title($session_id, $review_id, $student_id),
                'panel_id' => $panel_id,
                'panel_reviewers' => $panel_reviewers_with_totals,
                'attendance_status' => $this->assignments->get_attendance_status($review_id, $student_id),
                'mark_status' => $this->student_report_mark_status(
                    $session_id,
                    $review_id,
                    $student_id,
                    $panel_reviewers,
                    $criteria,
                    $coordinator_locked
                ),
                'reviewer_totals' => $reviewer_totals,
                'review_score' => (float) ($aggregate['review_score'] ?? 0),
            ], $panel_context);
        }

        $lock_readiness = (new MarkService(
            $this->sessions,
            $this->reviews,
            $this->assignments,
            $this->marks
        ))->review_lock_readiness($review_id);

        return [
            'review_id' => $review_id,
            'reviewers' => $reviewers,
            'students' => $students_payload,
            'max_panel_reviewer_slots' => $max_panel_reviewer_slots,
            'coordinator_marks_locked' => $coordinator_locked,
            'review_lock_ready' => !$coordinator_locked && $lock_readiness['review_lock_ready'],
            'unfrozen_panels' => $lock_readiness['unfrozen_panels'],
        ];
    }

    /**
     * Student-grain consolidated scores across all confirmed reviews (live Reports tab).
     *
     * Review score per review uses ScoreService::calculate_review_score() (weighted avg of
     * reviewer totals from submitted marks). Combined score uses calculate_combined_score().
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function consolidated_scores(int $session_id): array|\WP_Error
    {
        if ($this->sessions->find_by_id($session_id) === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $reviews_payload = [];
        foreach ($this->reviews->list_for_session($session_id) as $review) {
            if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
                continue;
            }

            $reviews_payload[] = [
                'review_id' => (int) ($review['id'] ?? 0),
                'label' => (string) ($review['label'] ?? ''),
                'sort_order' => (int) ($review['sort_order'] ?? 0),
            ];
        }

        usort(
            $reviews_payload,
            static function (array $a, array $b): int {
                $order = ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                if ($order !== 0) {
                    return $order;
                }

                return ($a['review_id'] ?? 0) <=> ($b['review_id'] ?? 0);
            }
        );

        $most_recent_review_id = 0;
        if ($reviews_payload !== []) {
            $most_recent_review_id = (int) ($reviews_payload[count($reviews_payload) - 1]['review_id'] ?? 0);
        }

        $students_payload = [];
        foreach ($this->list_enrolled_students($session_id) as $student) {
            $student_id = (int) ($student['id'] ?? 0);
            $combined = $this->scores->calculate_combined_score($session_id, $student_id);
            $project_title = $most_recent_review_id > 0
                ? $this->assignments->resolve_project_title($session_id, $most_recent_review_id, $student_id)
                : trim((string) ($student['project_title'] ?? ''));
            $per_review = [];

            foreach ($reviews_payload as $review_meta) {
                $review_id = (int) ($review_meta['review_id'] ?? 0);
                $panel_id = $this->panel_id_map_for_review($session_id, $review_id)[$student_id] ?? null;
                $panel_reviewers = $this->build_panel_reviewers_payload($review_id, $panel_id);
                $panel_context = $this->panel_context_for_student($review_id, $panel_id, $panel_reviewers);
                $aggregate = $this->scores->calculate_review_score($session_id, $student_id, $review_id);
                $review_score = ($aggregate['reviewers'] ?? []) !== []
                    ? (float) ($aggregate['review_score'] ?? 0)
                    : null;

                $per_review[] = array_merge([
                    'review_id' => $review_id,
                    'review_score' => $review_score,
                ], $panel_context);
            }

            $students_payload[] = [
                'student_id' => $student_id,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'program' => (string) ($student['program'] ?? ''),
                'batch' => (string) ($student['batch'] ?? ''),
                'guide_emp_id' => (string) ($student['guide_emp_id'] ?? ''),
                'guide_name' => (string) ($student['guide_name'] ?? ''),
                'project_title' => $project_title,
                'overall_score' => (float) ($combined['combined_score'] ?? 0),
                'reviews' => $per_review,
            ];
        }

        return [
            'session_id' => $session_id,
            'reviews' => $reviews_payload,
            'students' => $students_payload,
        ];
    }

    /**
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row?: int, header_row_count?: int, numeric_columns?: list<int>},
     *   filename: string
     * }|\WP_Error
     */
    public function consolidated_scores_export(
        int $session_id,
        string $sort_key,
        string $sort_dir
    ): array|\WP_Error {
        $sort_dir = strtolower($sort_dir) === 'desc' ? 'desc' : 'asc';

        $data = $this->consolidated_scores($session_id);
        if ($data instanceof \WP_Error) {
            return $data;
        }

        $session = $this->sessions->find_by_id($session_id);
        $session_slug = sanitize_title((string) ($session['title'] ?? 'session-' . $session_id));
        $filename = "{$session_slug}_consolidated_scores.xlsx";

        $reviews = $data['reviews'] ?? [];
        $rows = $this->build_consolidated_export_rows($reviews, $data['students'] ?? []);
        $sorted = $this->sort_consolidated_export_rows($rows, $reviews, $sort_key, $sort_dir);
        $built = $this->build_consolidated_export_sheet($reviews, $sorted);

        return [
            'rows' => $built['rows'],
            'merge_plan' => $built['merge_plan'],
            'styles' => $built['styles'],
            'filename' => $filename,
        ];
    }

    public const CONSOLIDATED_STUDENT_CSV_PATH_DELIMITER = ' | ';

    /**
     * Full hierarchical consolidated export: one row per enrolled student, rubric depth per reviewer slot.
     *
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   csv_rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row?: int, header_row_count?: int, numeric_columns?: list<int>, table_corner?: array<string,int>|null, column_fill_ranges?: list<array<string,mixed>>, header_fill_ranges?: list<array<string,mixed>>, column_widths?: list<array<string,mixed>>, wrap_text_table?: bool},
     *   filename: string
     * }|\WP_Error
     */
    public function consolidated_student_export(int $session_id): array|\WP_Error
    {
        if ($this->sessions->find_by_id($session_id) === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $review_specs = $this->build_consolidated_student_review_specs($session_id);
        $columns = $this->build_consolidated_student_columns($review_specs);
        $data_rows = $this->build_consolidated_student_data_rows($session_id, $review_specs);
        $sheet = $this->build_consolidated_student_export_sheet($session_id, $columns, $review_specs, $data_rows);

        $session = $this->sessions->find_by_id($session_id);
        $session_slug = sanitize_title((string) ($session['title'] ?? 'session-' . $session_id));

        return [
            'rows' => $sheet['rows'],
            'csv_rows' => $this->build_consolidated_student_csv_rows($columns, $data_rows),
            'merge_plan' => $sheet['merge_plan'],
            'styles' => $sheet['styles'],
            'filename' => "{$session_slug}_consolidated_student_scores",
        ];
    }

    public const PANEL_ROSTER_CATALOG_KEY = 'panel_roster';

    public const CONSOLIDATED_STUDENT_CATALOG_KEY = 'consolidated_student_scores';

    public const OFFLINE_SCORING_SHEET_CATALOG_KEY = 'offline_scoring_sheet';

    public const MARKS_MATRIX_CATALOG_KEY = 'marks_matrix';

    public const SCORES_MATRIX_CATALOG_KEY = 'scores_matrix';

    /**
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row?: int},
     *   filename: string
     * }|\WP_Error
     */
    public function panel_roster_export(int $session_id, int $review_id): array|\WP_Error
    {
        $review = $this->require_review($session_id, $review_id);
        if ($review instanceof \WP_Error) {
            return $review;
        }

        $review_label = (string) ($review['label'] ?? '');
        $max_slots = $this->compute_max_panel_reviewer_slots($session_id, $review_id);
        $panel_id_by_student = $this->panel_id_map_for_review($session_id, $review_id);

        $header = [
            'Review number',
            'Panel',
            'Panel coordinator',
            'Reg no',
            'Student name',
            'Program',
            'Batch',
            'Project title',
            'Guide emp. ID',
            'Guide name',
            'Attendance',
        ];
        for ($slot = 1; $slot <= $max_slots; $slot++) {
            $header[] = 'Reviewer ' . $slot;
        }

        $data_lines = [];
        foreach ($this->list_enrolled_students($session_id) as $student) {
            $student_id = (int) ($student['id'] ?? 0);
            $panel_id = $panel_id_by_student[$student_id] ?? null;
            if ($panel_id === null || $panel_id <= 0) {
                continue;
            }

            $panel_reviewers = $this->build_panel_reviewers_payload($review_id, $panel_id);
            $panel_context = $this->panel_context_for_student($review_id, $panel_id, $panel_reviewers);
            $reviewer_cells = array_fill(0, $max_slots, '');
            foreach ($panel_reviewers as $reviewer) {
                $slot_index = (int) ($reviewer['slot_index'] ?? 0);
                if ($slot_index >= 0 && $slot_index < $max_slots) {
                    $reviewer_cells[$slot_index] = (string) ($reviewer['name'] ?? '');
                }
            }

            $data_lines[] = [
                'panel_name' => (string) ($panel_context['panel_name'] ?? ''),
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'row' => array_merge(
                    [
                        $review_label,
                        (string) ($panel_context['panel_name'] ?? ''),
                        (string) ($panel_context['panel_coordinator_name'] ?? ''),
                        (string) ($student['reg_no'] ?? ''),
                        (string) ($student['name'] ?? ''),
                        (string) ($student['program'] ?? ''),
                        (string) ($student['batch'] ?? ''),
                        $this->assignments->resolve_project_title($session_id, $review_id, $student_id),
                        (string) ($student['guide_emp_id'] ?? ''),
                        (string) ($student['guide_name'] ?? ''),
                        $this->format_panel_roster_attendance(
                            $this->assignments->get_attendance_status($review_id, $student_id)
                        ),
                    ],
                    $reviewer_cells
                ),
            ];
        }

        usort(
            $data_lines,
            static fn (array $a, array $b): int => [$a['panel_name'], $a['reg_no']] <=> [$b['panel_name'], $b['reg_no']]
        );

        $rows = [$header];
        foreach ($data_lines as $line) {
            $rows[] = $line['row'];
        }

        $session = $this->sessions->find_by_id($session_id);
        $session_slug = sanitize_title((string) ($session['title'] ?? 'session-' . $session_id));
        $review_slug = sanitize_title($review_label !== '' ? $review_label : 'review-' . $review_id);

        return [
            'rows' => $rows,
            'merge_plan' => ExportService::merge_plan_for_columns($rows, [0, 1]),
            'styles' => ['freeze_row' => 1],
            'filename' => "{$session_slug}_{$review_slug}_panel_roster",
        ];
    }

    /**
     * @param list<array{review_id: int, label: string}> $reviews
     * @param list<array<string, mixed>> $students
     * @return list<array<string, mixed>>
     */
    private function build_consolidated_export_rows(array $reviews, array $students): array
    {
        $rows = [];
        foreach ($students as $student) {
            $review_by_id = [];
            foreach ($student['reviews'] ?? [] as $block) {
                $review_by_id[(int) ($block['review_id'] ?? 0)] = $block;
            }

            $cells = [];
            foreach ($reviews as $review_meta) {
                $review_id = (int) ($review_meta['review_id'] ?? 0);
                $block = $review_by_id[$review_id] ?? [];
                $cells['review_' . $review_id . '_panel'] = (string) ($block['panel_name'] ?? '');
                $cells['review_' . $review_id . '_panel_coordinator'] = (string) ($block['panel_coordinator_name'] ?? '');
                $cells['review_' . $review_id . '_reviewers'] = (string) ($block['panel_reviewer_names'] ?? '');
                $cells['review_' . $review_id . '_review_score'] = $block['review_score'] ?? null;
            }

            $rows[] = [
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'program' => (string) ($student['program'] ?? ''),
                'batch' => (string) ($student['batch'] ?? ''),
                'guide_emp_id' => (string) ($student['guide_emp_id'] ?? ''),
                'guide_name' => (string) ($student['guide_name'] ?? ''),
                'project_title' => (string) ($student['project_title'] ?? ''),
                'cells' => $cells,
                'overall_score' => $student['overall_score'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{review_id: int}> $reviews
     * @return list<array<string, mixed>>
     */
    private function sort_consolidated_export_rows(
        array $rows,
        array $reviews,
        string $sort_key,
        string $sort_dir
    ): array {
        $review_keys = [];
        foreach ($reviews as $review_meta) {
            $review_id = (int) ($review_meta['review_id'] ?? 0);
            $review_keys['review_' . $review_id . '_panel'] = true;
            $review_keys['review_' . $review_id . '_panel_coordinator'] = true;
            $review_keys['review_' . $review_id . '_reviewers'] = true;
            $review_keys['review_' . $review_id . '_review_score'] = true;
        }

        usort(
            $rows,
            function (array $a, array $b) use ($sort_key, $sort_dir, $review_keys): int {
                $value_a = $this->consolidated_sort_value($a, $sort_key, $review_keys);
                $value_b = $this->consolidated_sort_value($b, $sort_key, $review_keys);
                $cmp = $this->compare_sort_values($value_a, $value_b, $sort_dir);
                if ($cmp !== 0) {
                    return $sort_dir === 'desc' ? -$cmp : $cmp;
                }

                return strcmp((string) ($a['reg_no'] ?? ''), (string) ($b['reg_no'] ?? ''));
            }
        );

        return $rows;
    }

    /**
     * @param array<string, bool> $review_keys
     */
    private function consolidated_sort_value(array $row, string $sort_key, array $review_keys): mixed
    {
        if ($sort_key === 'reg_no') {
            return $row['reg_no'] ?? '';
        }
        if ($sort_key === 'name') {
            return $row['name'] ?? '';
        }
        if ($sort_key === 'program') {
            return $row['program'] ?? '';
        }
        if ($sort_key === 'batch') {
            return $row['batch'] ?? '';
        }
        if ($sort_key === 'guide_emp_id') {
            return $row['guide_emp_id'] ?? '';
        }
        if ($sort_key === 'guide_name') {
            return $row['guide_name'] ?? '';
        }
        if ($sort_key === 'project_title') {
            return $row['project_title'] ?? '';
        }
        if ($sort_key === 'overall_score') {
            return $row['overall_score'] ?? null;
        }
        if (!isset($review_keys[$sort_key])) {
            return null;
        }

        return $row['cells'][$sort_key] ?? null;
    }

    /**
     * @param list<array{review_id: int, label: string}> $reviews
     * @param list<array<string, mixed>> $sorted_rows
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row: int, header_row_count: int, numeric_columns: list<int>}
     * }
     */
    private function build_consolidated_export_sheet(array $reviews, array $sorted_rows): array
    {
        $fixed_count = 7;
        $sub_labels = ['Panel', 'Panel coordinator', 'Reviewers', 'Review score'];
        $colspans = [];
        foreach ($reviews as $_review) {
            $colspans[] = count($sub_labels);
        }

        $header1 = [
            'Reg no',
            'Student',
            'Program',
            'Batch',
            'Guide emp. ID',
            'Guide name',
            'Project title',
        ];
        foreach ($reviews as $review_meta) {
            $header1[] = (string) ($review_meta['label'] ?? '');
            for ($i = 1, $span = count($sub_labels); $i < $span; $i++) {
                $header1[] = '';
            }
        }
        $header1[] = 'Overall score';

        $header2 = array_fill(0, $fixed_count, '');
        foreach ($reviews as $_review) {
            foreach ($sub_labels as $label) {
                $header2[] = $label;
            }
        }
        $header2[] = '';

        $sheet_rows = [$header1, $header2];
        foreach ($sorted_rows as $row) {
            $line = [
                (string) ($row['reg_no'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['program'] ?? ''),
                (string) ($row['batch'] ?? ''),
                (string) ($row['guide_emp_id'] ?? ''),
                (string) ($row['guide_name'] ?? ''),
                (string) ($row['project_title'] ?? ''),
            ];
            foreach ($reviews as $review_meta) {
                $review_id = (int) ($review_meta['review_id'] ?? 0);
                $cells = $row['cells'] ?? [];
                $line[] = (string) ($cells['review_' . $review_id . '_panel'] ?? '');
                $line[] = (string) ($cells['review_' . $review_id . '_panel_coordinator'] ?? '');
                $line[] = (string) ($cells['review_' . $review_id . '_reviewers'] ?? '');
                $score = $cells['review_' . $review_id . '_review_score'] ?? null;
                $line[] = $score !== null ? $score : '';
            }
            $line[] = $row['overall_score'];
            $sheet_rows[] = $line;
        }

        $merge_plan = [];
        for ($col = 0; $col < $fixed_count; $col++) {
            $merge_plan[] = ['col' => $col, 'start_row' => 1, 'end_row' => 2];
        }
        $merge_plan = array_merge(
            $merge_plan,
            ExportService::merge_plan_for_row_groups(1, $fixed_count, $colspans)
        );
        $merge_plan[] = [
            'col' => count($header1) - 1,
            'start_row' => 1,
            'end_row' => 2,
        ];

        $numeric_columns = [count($header1) - 1];
        $score_col = $fixed_count;
        foreach ($reviews as $_review) {
            $numeric_columns[] = $score_col + 3;
            $score_col += count($sub_labels);
        }

        return [
            'rows' => $sheet_rows,
            'merge_plan' => $merge_plan,
            'styles' => [
                'freeze_row' => 2,
                'header_row_count' => 2,
                'numeric_columns' => $numeric_columns,
            ],
        ];
    }

    /**
     * @return list<array{
     *   review_id: int,
     *   label: string,
     *   criteria: list<array{id: int, label: string}>,
     *   max_slots: int,
     *   has_review_weight: bool,
     *   review_weight: float,
     *   marks_by_student: array<int, array<int|string, list<array<string, mixed>>>>,
     *   panel_id_by_student: array<int, int|null>
     * }>
     */
    private function build_consolidated_student_review_specs(int $session_id): array
    {
        $specs = [];
        foreach ($this->reviews->list_for_session($session_id) as $review) {
            if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
                continue;
            }

            $review_id = (int) ($review['id'] ?? 0);
            $criteria = [];
            foreach ($this->reviews->list_criteria($review_id) as $row) {
                $criteria[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'label' => (string) ($row['label'] ?? ''),
                ];
            }

            $specs[] = [
                'review_id' => $review_id,
                'label' => (string) ($review['label'] ?? ''),
                'sort_order' => (int) ($review['sort_order'] ?? 0),
                'criteria' => $criteria,
                'max_slots' => $this->compute_max_panel_reviewer_slots($session_id, $review_id),
                'has_review_weight' => $this->reviews->has_review_weight($session_id, $review_id),
                'review_weight' => $this->reviews->get_review_weight($session_id, $review_id),
                'marks_by_student' => $this->marks_by_student_criterion_for_review($session_id, $review_id),
                'panel_id_by_student' => $this->panel_id_map_for_review($session_id, $review_id),
            ];
        }

        usort(
            $specs,
            static function (array $a, array $b): int {
                $order = ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                if ($order !== 0) {
                    return $order;
                }

                return ($a['review_id'] ?? 0) <=> ($b['review_id'] ?? 0);
            }
        );

        return $specs;
    }

    /**
     * @param list<array{
     *   review_id: int,
     *   label: string,
     *   criteria: list<array{id: int, label: string}>,
     *   max_slots: int,
     *   has_review_weight: bool,
     *   review_weight: float
     * }> $review_specs
     * @return list<array{
     *   key: string,
     *   csv_header: string,
     *   numeric: bool,
     *   fixed?: bool,
     *   trailing?: bool,
     *   review_id?: int,
     *   merge_l2_vertical?: bool,
     *   merge_l2_horizontal_span?: int
     * }>
     */
    private function build_consolidated_student_columns(array $review_specs): array
    {
        $delim = self::CONSOLIDATED_STUDENT_CSV_PATH_DELIMITER;
        $columns = [
            [
                'key' => 'reg_no',
                'csv_header' => 'Reg no',
                'numeric' => false,
                'fixed' => true,
            ],
            [
                'key' => 'name',
                'csv_header' => 'Student name',
                'numeric' => false,
                'fixed' => true,
            ],
            [
                'key' => 'program',
                'csv_header' => 'Program',
                'numeric' => false,
                'fixed' => true,
            ],
            [
                'key' => 'batch',
                'csv_header' => 'Batch',
                'numeric' => false,
                'fixed' => true,
            ],
            [
                'key' => 'guide_emp_id',
                'csv_header' => 'Guide emp. ID',
                'numeric' => false,
                'fixed' => true,
            ],
            [
                'key' => 'guide_name',
                'csv_header' => 'Guide name',
                'numeric' => false,
                'fixed' => true,
            ],
        ];

        foreach ($review_specs as $spec) {
            $review_id = (int) ($spec['review_id'] ?? 0);
            $label = (string) ($spec['label'] ?? '');
            $prefix = $label . $delim;

            $columns[] = [
                'key' => 'review_' . $review_id . '_panel',
                'csv_header' => $prefix . 'Panel',
                'excel_h2' => 'Panel',
                'excel_h3' => '',
                'numeric' => false,
                'review_id' => $review_id,
                'merge_l2_vertical' => true,
            ];
            $columns[] = [
                'key' => 'review_' . $review_id . '_panel_coordinator',
                'csv_header' => $prefix . 'Panel coordinator',
                'excel_h2' => 'Panel coordinator',
                'excel_h3' => '',
                'numeric' => false,
                'review_id' => $review_id,
                'merge_l2_vertical' => true,
            ];
            $columns[] = [
                'key' => 'review_' . $review_id . '_reviewers',
                'csv_header' => $prefix . 'Reviewers',
                'excel_h2' => 'Reviewers',
                'excel_h3' => '',
                'numeric' => false,
                'review_id' => $review_id,
                'merge_l2_vertical' => true,
            ];

            $max_slots = max(0, (int) ($spec['max_slots'] ?? 0));
            $criteria = $spec['criteria'] ?? [];
            for ($slot = 0; $slot < $max_slots; $slot++) {
                $slot_label = sprintf(
                    /* translators: %d: reviewer slot number (1-based) */
                    __('Reviewer %d', 'project-reviews'),
                    $slot + 1
                );
                $slot_prefix = $prefix . $slot_label . $delim;
                $slot_span = 1 + count($criteria);

                if ($criteria === []) {
                    $columns[] = [
                        'key' => 'review_' . $review_id . '_slot_' . $slot . '_total',
                        'csv_header' => $slot_prefix . 'Total',
                        'excel_h2' => $slot_label,
                        'excel_h3' => 'Total',
                        'numeric' => true,
                        'review_id' => $review_id,
                    ];
                    continue;
                }

                foreach ($criteria as $criterion_index => $criterion) {
                    $criterion_label = (string) ($criterion['label'] ?? '');
                    $columns[] = [
                        'key' => 'review_' . $review_id . '_slot_' . $slot . '_c' . (int) ($criterion['id'] ?? 0),
                        'csv_header' => $slot_prefix . $criterion_label,
                        'excel_h2' => $criterion_index === 0 ? $slot_label : '',
                        'excel_h3' => $criterion_label,
                        'numeric' => true,
                        'review_id' => $review_id,
                        'merge_l2_horizontal_span' => $criterion_index === 0 ? $slot_span : 0,
                    ];
                }

                $columns[] = [
                    'key' => 'review_' . $review_id . '_slot_' . $slot . '_total',
                    'csv_header' => $slot_prefix . 'Total',
                    'excel_h2' => '',
                    'excel_h3' => 'Total',
                    'numeric' => true,
                    'review_id' => $review_id,
                ];
            }

            $columns[] = [
                'key' => 'review_' . $review_id . '_review_total',
                'csv_header' => $prefix . 'Review total',
                'excel_h2' => 'Review total',
                'excel_h3' => '',
                'numeric' => true,
                'review_id' => $review_id,
                'merge_l2_vertical' => true,
            ];
            $columns[] = [
                'key' => 'review_' . $review_id . '_review_weight',
                'csv_header' => $prefix . 'Review weight %',
                'excel_h2' => 'Review weight %',
                'excel_h3' => '',
                'numeric' => false,
                'review_id' => $review_id,
                'merge_l2_vertical' => true,
            ];
        }

        $columns[] = [
            'key' => 'combined_score',
            'csv_header' => 'Combined score',
            'numeric' => true,
            'fixed' => true,
            'trailing' => true,
        ];

        return $columns;
    }

    /**
     * @param list<array{
     *   review_id: int,
     *   label: string,
     *   criteria: list<array{id: int, label: string}>,
     *   max_slots: int,
     *   has_review_weight: bool,
     *   review_weight: float,
     *   marks_by_student: array<int, array<int|string, list<array<string, mixed>>>>,
     *   panel_id_by_student: array<int, int|null>
     * }> $review_specs
     * @return list<array<string, mixed>>
     */
    private function build_consolidated_student_data_rows(int $session_id, array $review_specs): array
    {
        $rows = [];
        foreach ($this->list_enrolled_students($session_id) as $student) {
            $student_id = (int) ($student['id'] ?? 0);
            $combined = $this->scores->calculate_combined_score($session_id, $student_id);

            $values = [
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'program' => (string) ($student['program'] ?? ''),
                'batch' => (string) ($student['batch'] ?? ''),
                'guide_emp_id' => (string) ($student['guide_emp_id'] ?? ''),
                'guide_name' => (string) ($student['guide_name'] ?? ''),
                'combined_score' => (float) ($combined['combined_score'] ?? 0),
            ];

            foreach ($review_specs as $spec) {
                $review_id = (int) ($spec['review_id'] ?? 0);
                $panel_id = $spec['panel_id_by_student'][$student_id] ?? null;
                $panel_reviewers = $this->build_panel_reviewers_payload($review_id, $panel_id);
                $panel_context = $this->panel_context_for_student($review_id, $panel_id, $panel_reviewers);
                $attendance = $this->assignments->get_attendance_status($review_id, $student_id);
                $marks_map = $this->marks_map_for_student_from_index(
                    $spec['marks_by_student'][$student_id] ?? [],
                    $spec['criteria'] ?? []
                );
                $marks_student = [
                    'marks' => $marks_map,
                    'panel_reviewers' => $panel_reviewers,
                    'attendance_status' => $attendance,
                ];

                $values['review_' . $review_id . '_panel'] = (string) ($panel_context['panel_name'] ?? '');
                $values['review_' . $review_id . '_panel_coordinator'] = (string) ($panel_context['panel_coordinator_name'] ?? '');
                $values['review_' . $review_id . '_reviewers'] = (string) ($panel_context['panel_reviewer_names'] ?? '');

                $aggregate = $this->scores->calculate_review_score($session_id, $student_id, $review_id);
                $values['review_' . $review_id . '_review_total'] = ($aggregate['reviewers'] ?? []) !== []
                    ? (float) ($aggregate['review_score'] ?? 0)
                    : null;
                $values['review_' . $review_id . '_review_weight'] = !empty($spec['has_review_weight'])
                    ? (float) ($spec['review_weight'] ?? 0)
                    : '';

                $max_slots = max(0, (int) ($spec['max_slots'] ?? 0));
                for ($slot = 0; $slot < $max_slots; $slot++) {
                    $reviewer_user_id = $this->reviewer_user_id_for_slot($marks_student, $slot);
                    if ($attendance === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
                        $values['review_' . $review_id . '_slot_' . $slot . '_total'] = null;
                    } elseif ($reviewer_user_id > 0) {
                        $values['review_' . $review_id . '_slot_' . $slot . '_total'] = $this->scores->calculate_reviewer_total(
                            $session_id,
                            $student_id,
                            $review_id,
                            $reviewer_user_id,
                            true
                        );
                    } else {
                        $values['review_' . $review_id . '_slot_' . $slot . '_total'] = null;
                    }

                    foreach ($spec['criteria'] ?? [] as $criterion) {
                        $criterion_id = (int) ($criterion['id'] ?? 0);
                        $cell = $this->score_cell_for_slot($marks_student, $criterion_id, $slot);
                        $key = 'review_' . $review_id . '_slot_' . $slot . '_c' . $criterion_id;
                        $values[$key] = ($cell !== null && $cell['score'] !== null) ? $cell['score'] : null;
                    }
                }
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @param array<int|string, list<array<string, mixed>>> $student_index
     * @param list<array{id: int}> $criteria
     * @return array<int|string, list<array<string, mixed>>>
     */
    private function marks_map_for_student_from_index(array $student_index, array $criteria): array
    {
        $marks_map = [];
        foreach ($criteria as $criterion) {
            $criterion_id = (int) ($criterion['id'] ?? 0);
            $marks_map[(string) $criterion_id] = $student_index[$criterion_id] ?? $student_index[(string) $criterion_id] ?? [];
        }

        return $marks_map;
    }

    /**
     * @return array<int, array<int|string, list<array<string, mixed>>>>
     */
    private function marks_by_student_criterion_for_review(int $session_id, int $review_id): array
    {
        $marks_by_student_criterion = [];
        foreach ($this->marks->list_for_review($review_id) as $mark) {
            if ((int) ($mark['session_id'] ?? 0) !== $session_id) {
                continue;
            }
            $student_id = (int) ($mark['student_id'] ?? 0);
            $criterion_id = (int) ($mark['criterion_id'] ?? 0);
            $reviewer_user_id = (int) ($mark['reviewer_user_id'] ?? 0);
            if ($student_id <= 0 || $criterion_id <= 0 || $reviewer_user_id <= 0) {
                continue;
            }

            $marks_by_student_criterion[$student_id][$criterion_id][] = [
                'reviewer_user_id' => $reviewer_user_id,
                'score' => $mark['score'] !== null ? (float) $mark['score'] : null,
                'status' => (string) ($mark['status'] ?? MarkRepository::STATUS_DRAFT),
            ];
        }

        return $marks_by_student_criterion;
    }

    /**
     * @param list<array{
     *   key: string,
     *   csv_header: string,
     *   numeric: bool,
     *   fixed?: bool,
     *   trailing?: bool,
     *   review_id?: int,
     *   merge_l2_vertical?: bool,
     *   merge_l2_horizontal_span?: int,
     *   span_pad?: bool
     * }> $columns
     * @return list<array{
     *   key: string,
     *   numeric?: bool,
     *   fixed?: bool,
     *   trailing?: bool,
     *   review_id?: int,
     *   merge_l2_vertical?: bool,
     *   merge_l2_horizontal_span?: int,
     *   span_pad?: bool,
     *   csv_header?: string,
     *   excel_h2?: string,
     *   excel_h3?: string
     * }>
     */
    private function expand_consolidated_columns(array $columns): array
    {
        // Horizontal merges for reviewer slots span real data columns only (rubric cols + total).
        // Do not inject span_pad rows here — that duplicated physical columns and broke alignment.
        return $columns;
    }

    /**
     * @param list<array{label: string}> $review_specs
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   row_count: int
     * }
     */
    private function build_consolidated_student_project_preface(int $session_id, array $review_specs): array
    {
        $session = $this->sessions->find_by_id($session_id);
        $title = (string) ($session['title'] ?? '');
        $status = $this->consolidated_session_status_label((string) ($session['status'] ?? SessionRepository::STATUS_DRAFT));

        $generated = function_exists('wp_date')
            ? wp_date(
                (string) (function_exists('get_option') ? get_option('date_format', 'Y-m-d') : 'Y-m-d')
                . ' '
                . (string) (function_exists('get_option') ? get_option('time_format', 'H:i') : 'H:i')
            )
            : gmdate('Y-m-d H:i');

        $review_labels = [];
        foreach ($review_specs as $spec) {
            $label = trim((string) ($spec['label'] ?? ''));
            if ($label !== '') {
                $review_labels[] = $label;
            }
        }

        $settings = SessionPanelReportSettings::get($session_id);
        $report_cfg = is_array($settings['report'] ?? null) ? $settings['report'] : [];
        $program = trim((string) ($report_cfg['program_name'] ?? ''));
        $semester = trim((string) ($report_cfg['semester'] ?? ''));

        $preface_rows = [
            [__('Consolidated student scores', 'project-reviews'), ''],
            [__('Project', 'project-reviews'), $title],
            [__('Project status', 'project-reviews'), $status],
            [__('Generated', 'project-reviews'), $generated],
            [__('Enrolled students', 'project-reviews'), (string) $this->sessions->count_enrolled($session_id)],
            [__('Reviews included', 'project-reviews'), implode(', ', $review_labels)],
        ];

        if ($program !== '') {
            $preface_rows[] = [__('Program', 'project-reviews'), $program];
        }
        if ($semester !== '') {
            $preface_rows[] = [__('Semester', 'project-reviews'), $semester];
        }

        $preface_rows[] = ['', ''];

        $merge_plan = [
            ['start_col' => 0, 'end_col' => 1, 'row' => 1],
        ];

        return [
            'rows' => $preface_rows,
            'merge_plan' => $merge_plan,
            'row_count' => count($preface_rows),
        ];
    }

    private function consolidated_session_status_label(string $status): string
    {
        return match ($status) {
            SessionRepository::STATUS_ACTIVE => __('Active', 'project-reviews'),
            SessionRepository::STATUS_CLOSED => __('Closed', 'project-reviews'),
            default => __('Draft', 'project-reviews'),
        };
    }

    /**
     * @param list<array{span_pad?: bool, key?: string}> $expanded_columns
     * @param array<string, mixed> $row
     * @return list<string|int|float|null>
     */
    private function build_consolidated_student_sheet_data_line(array $expanded_columns, array $row): array
    {
        $line = [];
        foreach ($expanded_columns as $column) {
            if (!empty($column['span_pad'])) {
                $line[] = '';
                continue;
            }
            $value = $row[(string) ($column['key'] ?? '')] ?? null;
            $line[] = ($value === null || $value === '') ? '' : $value;
        }

        return $line;
    }

    /**
     * @param list<array<string, int>> $merge_plan
     * @return list<array<string, int>>
     */
    private function offset_merge_plan_rows(array $merge_plan, int $row_offset): array
    {
        if ($row_offset === 0) {
            return $merge_plan;
        }

        $offsetted = [];
        foreach ($merge_plan as $merge) {
            $item = $merge;
            if (isset($item['start_row'])) {
                $item['start_row'] += $row_offset;
                $item['end_row'] += $row_offset;
            }
            if (isset($item['row'])) {
                $item['row'] += $row_offset;
            }
            $offsetted[] = $item;
        }

        return $offsetted;
    }

    /**
     * @return array{0: int, 1: int, 2: int} RGB 0–255
     */
    private static function consolidated_hsl_to_rgb(float $hueDegrees, float $saturationPercent, float $lightnessPercent): array
    {
        $h = fmod($hueDegrees / 360.0 + 1.0, 1.0);
        $s = max(0.0, min(1.0, $saturationPercent / 100.0));
        $l = max(0.0, min(1.0, $lightnessPercent / 100.0));

        if ($s < 1e-8) {
            $v = (int) round($l * 255.0);

            return [$v, $v, $v];
        }

        $hue2rgb = static function (float $p, float $q, float $t): float {
            if ($t < 0.0) {
                $t += 1.0;
            }
            if ($t > 1.0) {
                $t -= 1.0;
            }
            if ($t < 1.0 / 6.0) {
                return $p + ($q - $p) * 6.0 * $t;
            }
            if ($t < 0.5) {
                return $q;
            }
            if ($t < 2.0 / 3.0) {
                return $p + ($q - $p) * (2.0 / 3.0 - $t) * 6.0;
            }

            return $p;
        };

        $q = $l < 0.5 ? $l * (1.0 + $s) : $l + $s - $l * $s;
        $p = 2.0 * $l - $q;
        $r = $hue2rgb($p, $q, $h + 1.0 / 3.0);
        $g = $hue2rgb($p, $q, $h);
        $b = $hue2rgb($p, $q, $h - 1.0 / 3.0);

        return [
            (int) max(0, min(255, round($r * 255.0))),
            (int) max(0, min(255, round($g * 255.0))),
            (int) max(0, min(255, round($b * 255.0))),
        ];
    }

    private static function consolidated_review_hue(int $reviewBandIndex, int $reviewCount): float
    {
        if ($reviewCount <= 0) {
            return 215.0;
        }

        return fmod(360.0 * $reviewBandIndex / $reviewCount + 12.0, 360.0);
    }

    private static function consolidated_argb_from_hsl(float $h, float $s, float $l): string
    {
        [$r, $g, $b] = self::consolidated_hsl_to_rgb($h, $s, $l);

        return sprintf('FF%02X%02X%02X', $r, $g, $b);
    }

    private static function consolidated_header_base_argb(int $reviewBandIndex, int $reviewCount): string
    {
        $h = self::consolidated_review_hue($reviewBandIndex, $reviewCount);

        return self::consolidated_argb_from_hsl($h, 27.0, 90.5);
    }

    private static function consolidated_header_slot_argb(int $reviewBandIndex, int $reviewCount, int $slotIndex, int $maxSlots): string
    {
        $h = self::consolidated_review_hue($reviewBandIndex, $reviewCount);
        $slotSpan = max(1, $maxSlots);
        $s = 27.0 - min(14.0, $slotIndex * 3.0);
        $phase = $slotSpan > 1 ? (($slotIndex % 3) - 1) * 0.9 : 0.0;
        $l = 90.5 + $phase;
        if ($slotSpan > 1) {
            $l += ($slotIndex / ($slotSpan - 1.0)) * 1.2;
        }

        return self::consolidated_argb_from_hsl($h, max(14.0, $s), max(87.0, min(93.5, $l)));
    }

    private static function consolidated_body_argb(
        int $reviewBandIndex,
        int $reviewCount,
        ?int $slotIndex,
        int $maxSlots
    ): string {
        $h = self::consolidated_review_hue($reviewBandIndex, $reviewCount);
        if ($slotIndex !== null && $maxSlots > 1) {
            $s = 11.0 + ($slotIndex % 2) * 1.5;
            $l = 97.2 + (($slotIndex % 3) - 1) * 0.35;

            return self::consolidated_argb_from_hsl($h, $s, max(96.5, min(98.2, $l)));
        }

        return self::consolidated_argb_from_hsl($h, 9.0, 97.4);
    }

    /**
     * @param list<array{start_col: int, end_col: int, min_row: int, max_row: int, fillArgb: string}> $ranges
     */
    private function consolidated_coalesce_fill_range(
        array &$ranges,
        int $startCol,
        int $endCol,
        int $minRow,
        int $maxRow,
        string $argb
    ): void {
        $n = count($ranges);
        if ($n > 0) {
            $lastIdx = $n - 1;
            $last = &$ranges[$lastIdx];
            if (
                $last['fillArgb'] === $argb
                && $last['min_row'] === $minRow
                && $last['max_row'] === $maxRow
                && $last['end_col'] + 1 === $startCol
            ) {
                $last['end_col'] = $endCol;

                return;
            }
        }
        $ranges[] = [
            'start_col' => $startCol,
            'end_col' => $endCol,
            'min_row' => $minRow,
            'max_row' => $maxRow,
            'fillArgb' => $argb,
        ];
    }

    /**
     * @param list<array<string, mixed>> $expanded_columns
     * @param list<array{review_id: int, label: string, criteria: list<array{id: int, label: string}>, max_slots: int}> $review_specs
     * @param list<array{start_col: int, end_col: int, review_index: int}> $review_col_intervals
     * @return array{
     *   header_fill_ranges: list<array{start_col: int, end_col: int, min_row: int, max_row: int, fillArgb: string}>,
     *   column_fill_ranges: list<array{start_col: int, end_col: int, min_row: int, max_row: int, fillArgb: string}>
     * }
     */
    private function consolidated_colour_fill_plans(
        array $expanded_columns,
        array $review_specs,
        int $preface_row_count,
        int $header_rows,
        int $data_start_row,
        int $total_table_max_row,
        array $review_col_intervals
    ): array {
        $neutral_header = 'FFE8EEF4';
        $neutral_body = 'FFFFFFFF';

        $review_id_to_band = [];
        $review_id_to_max_slots = [];
        foreach ($review_specs as $bandIdx => $spec) {
            $rid = (int) ($spec['review_id'] ?? 0);
            $review_id_to_band[$rid] = $bandIdx;
            $review_id_to_max_slots[$rid] = max(0, (int) ($spec['max_slots'] ?? 0));
        }
        $review_count = count($review_specs);

        $header_fill_ranges = [];
        $column_fill_ranges = [];

        $table_header_start = $preface_row_count + 1;
        $table_header_end = $preface_row_count + $header_rows;

        $col = 0;
        foreach ($expanded_columns as $column) {
            if (!empty($column['span_pad'])) {
                $this->consolidated_coalesce_fill_range(
                    $header_fill_ranges,
                    $col,
                    $col,
                    $table_header_start,
                    $table_header_end,
                    $neutral_header
                );
                if ($data_start_row <= $total_table_max_row) {
                    $this->consolidated_coalesce_fill_range(
                        $column_fill_ranges,
                        $col,
                        $col,
                        $data_start_row,
                        $total_table_max_row,
                        $neutral_body
                    );
                }
                $col++;
                continue;
            }

            $is_fixed = !empty($column['fixed']) && empty($column['trailing']);
            $is_trailing = !empty($column['trailing']);
            $key = (string) ($column['key'] ?? '');

            if ($is_fixed || $is_trailing) {
                $this->consolidated_coalesce_fill_range(
                    $header_fill_ranges,
                    $col,
                    $col,
                    $table_header_start,
                    $table_header_end,
                    $neutral_header
                );
                if ($data_start_row <= $total_table_max_row) {
                    $this->consolidated_coalesce_fill_range(
                        $column_fill_ranges,
                        $col,
                        $col,
                        $data_start_row,
                        $total_table_max_row,
                        $neutral_body
                    );
                }
                $col++;
                continue;
            }

            $review_id = (int) ($column['review_id'] ?? 0);
            $band = $review_id_to_band[$review_id] ?? 0;
            $max_slots = $review_id_to_max_slots[$review_id] ?? 0;
            $slot_index = null;
            if (preg_match('/_slot_(\d+)_/', $key, $m)) {
                $slot_index = (int) $m[1];
            }

            $is_slot_band = $slot_index !== null;
            $deep_argb = $is_slot_band
                ? self::consolidated_header_slot_argb($band, $review_count, $slot_index, $max_slots)
                : self::consolidated_header_base_argb($band, $review_count);

            if ($table_header_end > $table_header_start) {
                $this->consolidated_coalesce_fill_range(
                    $header_fill_ranges,
                    $col,
                    $col,
                    $table_header_start + 1,
                    $table_header_end,
                    $deep_argb
                );
            }

            if ($data_start_row <= $total_table_max_row) {
                $body_argb = self::consolidated_body_argb($band, $review_count, $slot_index, $max_slots);
                $this->consolidated_coalesce_fill_range(
                    $column_fill_ranges,
                    $col,
                    $col,
                    $data_start_row,
                    $total_table_max_row,
                    $body_argb
                );
            }

            $col++;
        }

        foreach ($review_col_intervals as $interval) {
            $bi = (int) ($interval['review_index'] ?? 0);
            $rs = (int) $interval['start_col'];
            $re = (int) $interval['end_col'];
            $base = self::consolidated_header_base_argb($bi, $review_count);
            $header_fill_ranges[] = [
                'start_col' => $rs,
                'end_col' => $re,
                'min_row' => $table_header_start,
                'max_row' => $table_header_start,
                'fillArgb' => $base,
            ];
        }

        return [
            'header_fill_ranges' => $header_fill_ranges,
            'column_fill_ranges' => $column_fill_ranges,
        ];
    }

    /**
     * Excel column width (character units) for consolidated student export columns.
     *
     * @param array{
     *   key?: string,
     *   fixed?: bool,
     *   trailing?: bool,
     *   numeric?: bool,
     *   excel_h2?: string
     * } $column
     */
    private function consolidated_export_column_width(array $column): float
    {
        $is_fixed = !empty($column['fixed']) && empty($column['trailing']);
        $is_trailing = !empty($column['trailing']);
        $key = (string) ($column['key'] ?? '');

        if ($is_fixed) {
            return match ($key) {
                'name' => 26.0,
                'guide_name' => 24.0,
                'reg_no' => 12.0,
                'program' => 18.0,
                'batch' => 11.0,
                'guide_emp_id' => 14.0,
                default => 15.0,
            };
        }

        if ($is_trailing) {
            return 16.0;
        }

        $h2 = (string) ($column['excel_h2'] ?? '');
        if ($h2 === 'Reviewers' || $h2 === 'Panel coordinator') {
            return 22.0;
        }
        if ($h2 === 'Panel') {
            return 14.0;
        }
        if (!empty($column['numeric'])) {
            return 12.0;
        }

        return 16.0;
    }

    /**
     * @param list<array{
     *   key: string,
     *   csv_header: string,
     *   numeric: bool,
     *   fixed?: bool,
     *   trailing?: bool,
     *   review_id?: int,
     *   merge_l2_vertical?: bool,
     *   merge_l2_horizontal_span?: int
     * }> $columns
     * @param list<array{
     *   review_id: int,
     *   label: string,
     *   criteria: list<array{id: int, label: string}>,
     *   max_slots: int
     * }> $review_specs
     * @param list<array<string, mixed>> $data_rows
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{
     *     freeze_row: int,
     *     header_row_count: int,
     *     preface_row_count: int,
     *     data_start_row: int,
     *     numeric_columns: list<int>,
     *     table_corner: array{min_row: int, max_row: int, min_col: int, max_col: int}|null,
     *     column_fill_ranges: list<array{start_col: int, end_col: int, fillArgb: string, min_row: int, max_row: int}>,
     *     header_fill_ranges: list<array{start_col: int, end_col: int, min_row: int, max_row: int, fillArgb: string}>,
     *     column_widths: list<array{col: int, width: float}>,
     *     wrap_text_table: bool
     *   }
     * }
     */
    private function build_consolidated_student_export_sheet(
        int $session_id,
        array $columns,
        array $review_specs,
        array $data_rows
    ): array {
        $has_rubric_depth = false;
        foreach ($review_specs as $spec) {
            if ((int) ($spec['max_slots'] ?? 0) > 0) {
                $has_rubric_depth = true;
                break;
            }
        }

        $header_rows = $has_rubric_depth ? 3 : 2;
        $fixed_leading = 6;
        $review_labels_by_id = [];
        foreach ($review_specs as $spec) {
            $review_labels_by_id[(int) ($spec['review_id'] ?? 0)] = (string) ($spec['label'] ?? '');
        }

        $expanded_columns = $this->expand_consolidated_columns($columns);

        $header1 = [];
        $header2 = [];
        $header3 = $has_rubric_depth ? [] : null;
        $merge_plan = [];
        $numeric_columns = [];
        $review_colspans = [];
        $current_review_id = null;
        $review_span_start = 0;
        $col_index = 0;

        // Track per-review column intervals for fill banding (0-based col indices).
        $review_col_intervals = [];
        $current_review_band_start_col = null;
        $review_band_index = -1;

        foreach ($expanded_columns as $column) {
            if (!empty($column['span_pad'])) {
                $header1[] = '';
                $header2[] = '';
                if ($header3 !== null) {
                    $header3[] = '';
                }
                $col_index++;
                continue;
            }

            $is_fixed = !empty($column['fixed']) && empty($column['trailing']);
            $is_trailing = !empty($column['trailing']);
            $review_id = (int) ($column['review_id'] ?? 0);

            if ($is_fixed || $is_trailing) {
                if ($is_trailing && $current_review_id !== null) {
                    $review_colspans[] = $col_index - $review_span_start;
                    $review_col_intervals[] = [
                        'start_col'    => $current_review_band_start_col,
                        'end_col'      => $col_index - 1,
                        'review_index' => $review_band_index,
                    ];
                    $current_review_band_start_col = null;
                    $current_review_id = null;
                }
                $label = (string) ($column['csv_header'] ?? '');
                $header1[] = $label;
                $header2[] = '';
                if ($header3 !== null) {
                    $header3[] = '';
                }
                $merge_plan[] = ['col' => $col_index, 'start_row' => 1, 'end_row' => $header_rows];
            } else {
                if ($review_id !== $current_review_id) {
                    if ($current_review_id !== null) {
                        $review_colspans[] = $col_index - $review_span_start;
                        $review_col_intervals[] = [
                            'start_col'    => $current_review_band_start_col,
                            'end_col'      => $col_index - 1,
                            'review_index' => $review_band_index,
                        ];
                    }
                    $current_review_id = $review_id;
                    $review_span_start = $col_index;
                    $review_band_index++;
                    $current_review_band_start_col = $col_index;
                    $header1[] = $review_labels_by_id[$review_id] ?? '';
                } else {
                    $header1[] = '';
                }

                $header2[] = (string) ($column['excel_h2'] ?? '');
                if ($header3 !== null) {
                    $header3[] = (string) ($column['excel_h3'] ?? '');
                }

                if (!empty($column['merge_l2_vertical'])) {
                    $merge_plan[] = ['col' => $col_index, 'start_row' => 2, 'end_row' => $header_rows];
                }
                $span = (int) ($column['merge_l2_horizontal_span'] ?? 0);
                if ($span > 1) {
                    $merge_plan[] = [
                        'start_col' => $col_index,
                        'end_col' => $col_index + $span - 1,
                        'row' => 2,
                    ];
                }
            }

            if (!empty($column['numeric'])) {
                $numeric_columns[] = $col_index;
            }
            $col_index++;
        }

        if ($current_review_id !== null) {
            $review_colspans[] = $col_index - $review_span_start;
            $review_col_intervals[] = [
                'start_col'    => $current_review_band_start_col,
                'end_col'      => $col_index - 1,
                'review_index' => $review_band_index,
            ];
        }

        $merge_plan = array_merge(
            $merge_plan,
            ExportService::merge_plan_for_row_groups(1, $fixed_leading, $review_colspans)
        );

        $table_rows = [$header1, $header2];
        if ($header3 !== null) {
            $table_rows[] = $header3;
        }

        foreach ($data_rows as $row) {
            $table_rows[] = $this->build_consolidated_student_sheet_data_line($expanded_columns, $row);
        }

        $preface = $this->build_consolidated_student_project_preface($session_id, $review_specs);
        $preface_row_count = $preface['row_count'];
        $table_row_offset = $preface_row_count;

        $sheet_rows = array_merge($preface['rows'], $table_rows);
        $merge_plan = array_merge(
            $preface['merge_plan'],
            $this->offset_merge_plan_rows($merge_plan, $table_row_offset)
        );

        $freeze_row = $preface_row_count + $header_rows;
        $data_start_row = $freeze_row + 1;

        $total_data_rows = count($data_rows);
        $total_table_max_row = $preface_row_count + $header_rows + $total_data_rows;

        $fills = $this->consolidated_colour_fill_plans(
            $expanded_columns,
            $review_specs,
            $preface_row_count,
            $header_rows,
            $data_start_row,
            $total_table_max_row,
            $review_col_intervals
        );
        $column_fill_ranges = $fills['column_fill_ranges'];
        $header_fill_ranges = $fills['header_fill_ranges'];

        $table_corner = ($col_index > 0 && $total_table_max_row > $preface_row_count) ? [
            'min_row' => $preface_row_count + 1,
            'max_row' => $total_table_max_row,
            'min_col' => 1,
            'max_col' => $col_index,
        ] : null;

        $column_widths = [];
        $wcol = 0;
        foreach ($expanded_columns as $column) {
            if (!empty($column['span_pad'])) {
                $wcol++;
                continue;
            }
            $column_widths[] = [
                'col'   => $wcol,
                'width' => $this->consolidated_export_column_width($column),
            ];
            $wcol++;
        }

        return [
            'rows' => $sheet_rows,
            'merge_plan' => $merge_plan,
            'styles' => [
                'preface_row_count'  => $preface_row_count,
                'freeze_row'         => $freeze_row,
                'header_row_count'   => $header_rows,
                'data_start_row'     => $data_start_row,
                'numeric_columns'    => $numeric_columns,
                'table_corner'       => $table_corner,
                'column_fill_ranges' => $column_fill_ranges,
                'header_fill_ranges' => $header_fill_ranges,
                'column_widths'      => $column_widths,
                'wrap_text_table'    => true,
            ],
        ];
    }

    /**
     * @param list<array{key: string, csv_header: string}> $columns
     * @param list<array<string, mixed>> $data_rows
     * @return list<list<string|int|float|null>>
     */
    private function build_consolidated_student_csv_rows(array $columns, array $data_rows): array
    {
        $header = [];
        foreach ($columns as $column) {
            $header[] = (string) ($column['csv_header'] ?? '');
        }

        $rows = [$header];
        foreach ($data_rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $value = $row[(string) ($column['key'] ?? '')] ?? null;
                $line[] = ($value === null || $value === '') ? '' : $value;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Panel-scoped overall scores (one panel's students and reviewers only).
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function scores_matrix_for_panel(int $session_id, int $review_id, int $panel_id): array|\WP_Error
    {
        $review = $this->require_confirmed_review($session_id, $review_id);
        if ($review instanceof \WP_Error) {
            return $review;
        }

        $panel = $this->panels->find_by_id($panel_id);
        if ($panel === null || (int) ($panel['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error(
                'pr_panel_not_found',
                __('Panel not found in this project.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $criteria = $this->reviews->list_criteria($review_id);
        $criteria_count = count($criteria);

        $coordinator_user_id = 0;
        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            if (!empty($row['is_panel_head'])) {
                $coordinator_user_id = (int) ($row['user_id'] ?? 0);
                break;
            }
        }

        $reviewers = [];
        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $reviewers[] = [
                'user_id' => $user_id,
                'name' => $this->reviewer_display_name($review_id, $user_id),
                'is_panel_coordinator' => $user_id > 0 && $user_id === $coordinator_user_id,
            ];
        }

        usort(
            $reviewers,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $ordinal = 1;
        foreach ($reviewers as &$reviewer) {
            $reviewer['ordinal'] = $ordinal;
            $reviewer['reviewer_ordinal'] = $ordinal;
            ++$ordinal;
        }
        unset($reviewer);

        $coordinator_locked = $this->reviews->is_coordinator_marks_locked($review_id);

        $students_payload = [];
        foreach ($this->assignments->list_student_panels($review_id) as $assignment) {
            if ((int) ($assignment['panel_id'] ?? 0) !== $panel_id) {
                continue;
            }

            $student_id = (int) ($assignment['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $student = $this->students->find_by_id($student_id);
            if ($student === null) {
                continue;
            }

            $enrolment = $this->sessions->find_enrolment($session_id, $student_id);

            $reviewer_totals = [];
            foreach ($reviewers as $reviewer) {
                $user_id = (int) ($reviewer['user_id'] ?? 0);
                $reviewer_totals[(string) $user_id] = $this->reviewer_total_cell_for_panel_report(
                    $session_id,
                    $review_id,
                    $student_id,
                    $user_id,
                    $criteria_count
                );
            }

            $aggregate = $this->scores->calculate_review_score($session_id, $student_id, $review_id);

            $students_payload[] = [
                'student_id' => $student_id,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'guide_name' => trim((string) ($enrolment['guide_name'] ?? '')),
                'project_title' => $this->assignments->resolve_project_title($session_id, $review_id, $student_id),
                'attendance_status' => $this->assignments->get_attendance_status($review_id, $student_id),
                'mark_status' => $this->student_report_mark_status(
                    $session_id,
                    $review_id,
                    $student_id,
                    $reviewers,
                    $criteria,
                    $coordinator_locked
                ),
                'reviewer_totals' => $reviewer_totals,
                'review_score' => (float) ($aggregate['review_score'] ?? 0),
            ];
        }

        usort(
            $students_payload,
            static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $session = $this->sessions->find_by_id($session_id);

        return [
            'session_id' => $session_id,
            'session_title' => (string) ($session['title'] ?? ''),
            'review_id' => $review_id,
            'review_label' => (string) ($review['label'] ?? ''),
            'panel_id' => $panel_id,
            'panel_name' => (string) ($panel['name'] ?? ''),
            'reviewers' => $reviewers,
            'students' => $students_payload,
            'coordinator_marks_locked' => $this->reviews->is_coordinator_marks_locked($review_id),
            'panel_frozen' => (new \ProjectReviews\Repositories\PanelFreezeRepository())->is_frozen($review_id, $panel_id),
        ];
    }

    /**
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row?: int, header_row_count?: int, numeric_columns?: list<int>},
     *   filename: string
     * }|\WP_Error
     */
    public function marks_grid_export(
        int $session_id,
        int $review_id,
        string $layout,
        string $sort_key,
        string $sort_dir
    ): array|\WP_Error {
        $layout = $layout === 'reviewer' ? 'reviewer' : 'rubric';
        $sort_dir = strtolower($sort_dir) === 'desc' ? 'desc' : 'asc';

        $marks = $this->marks_grid($session_id, $review_id);
        if ($marks instanceof \WP_Error) {
            return $marks;
        }

        $scores = $this->scores_matrix($session_id, $review_id);
        if ($scores instanceof \WP_Error) {
            return $scores;
        }

        $review = $this->reviews->find_by_id($review_id);
        $session = $this->sessions->find_by_id($session_id);
        $session_slug = sanitize_title((string) ($session['title'] ?? 'session-' . $session_id));
        $review_slug = sanitize_title((string) ($review['label'] ?? 'review-' . $review_id));
        $filename = "{$session_slug}_{$review_slug}_marks_{$layout}.xlsx";

        $criteria = $marks['criteria'] ?? [];
        $max_slots = (int) ($marks['max_panel_reviewer_slots'] ?? 0);
        $columns = $this->build_export_columns($layout, $criteria, $max_slots);
        $rows = $this->build_export_rows(
            $marks['students'] ?? [],
            $scores['students'] ?? [],
            $columns,
            $max_slots
        );
        $sorted = $this->sort_export_rows($rows, $columns, $sort_key, $sort_dir);
        $built = $this->build_marks_export_sheet($columns, $sorted);

        return [
            'rows' => $built['rows'],
            'merge_plan' => $built['merge_plan'],
            'styles' => $built['styles'],
            'filename' => $filename,
        ];
    }

    /**
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row?: int, header_row_count?: int, numeric_columns?: list<int>},
     *   filename: string
     * }|\WP_Error
     */
    public function scores_matrix_export(
        int $session_id,
        int $review_id,
        string $sort_key,
        string $sort_dir
    ): array|\WP_Error {
        $sort_dir = strtolower($sort_dir) === 'desc' ? 'desc' : 'asc';

        $scores = $this->scores_matrix($session_id, $review_id);
        if ($scores instanceof \WP_Error) {
            return $scores;
        }

        $review = $this->reviews->find_by_id($review_id);
        $session = $this->sessions->find_by_id($session_id);
        $session_slug = sanitize_title((string) ($session['title'] ?? 'session-' . $session_id));
        $review_slug = sanitize_title((string) ($review['label'] ?? 'review-' . $review_id));
        $filename = "{$session_slug}_{$review_slug}_scores.xlsx";

        $max_slots = (int) ($scores['max_panel_reviewer_slots'] ?? 0);
        $columns = $this->build_scores_export_columns($max_slots);
        $rows = $this->build_scores_export_rows($scores['students'] ?? [], $max_slots);
        $sorted = $this->sort_scores_export_rows($rows, $columns, $sort_key, $sort_dir);
        $built = $this->build_scores_export_sheet($columns, $sorted);

        return [
            'rows' => $built['rows'],
            'merge_plan' => $built['merge_plan'],
            'styles' => $built['styles'],
            'filename' => $filename,
        ];
    }

    /**
     * @return array{
     *   reviewer_slots: list<array{key: string, label: string, slot_index: int}>,
     *   groups: list<array{id: string, label: string, leaves: list<array{key: string, label: string, slot_index: int}>>
     * }
     */
    private function build_scores_export_columns(int $max_panel_reviewer_slots): array
    {
        $reviewer_slots = [];
        for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
            $reviewer_slots[] = [
                'key' => 'reviewer_slot_' . $slot,
                'label' => sprintf(
                    /* translators: %d: reviewer slot number (1-based) */
                    __('Reviewer %d', 'project-reviews'),
                    $slot + 1
                ),
                'slot_index' => $slot,
            ];
        }

        $leaves = [];
        for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
            $leaves[] = [
                'key' => $this->overall_slot_key($slot),
                'label' => sprintf(
                    /* translators: %d: reviewer slot number (1-based) */
                    __('Reviewer %d', 'project-reviews'),
                    $slot + 1
                ),
                'slot_index' => $slot,
            ];
        }

        return [
            'reviewer_slots' => $reviewer_slots,
            'groups' => [
                [
                    'id' => 'reviewer-overall',
                    'label' => __('Reviewer overall', 'project-reviews'),
                    'leaves' => $leaves,
                ],
            ],
        ];
    }

    private function overall_slot_key(int $slot_index): string
    {
        return 'overall_s' . $slot_index;
    }

    /**
     * @param list<array<string, mixed>> $score_students
     * @return list<array<string, mixed>>
     */
    private function build_scores_export_rows(array $score_students, int $max_panel_reviewer_slots): array
    {
        $leaf_slots = [];
        for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
            $leaf_slots[$this->overall_slot_key($slot)] = $slot;
        }

        $rows = [];
        foreach ($score_students as $student) {
            $cells = [];
            foreach ($leaf_slots as $key => $slot_index) {
                $cells[$key] = $this->overall_cell_for_slot($student, $slot_index);
            }

            $rows[] = [
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'panel_name' => (string) ($student['panel_name'] ?? ''),
                'panel_coordinator_name' => (string) ($student['panel_coordinator_name'] ?? ''),
                'panel_reviewer_names' => (string) ($student['panel_reviewer_names'] ?? ''),
                'cells' => $cells,
                'review_score' => $student['review_score'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $student
     * @return array{score: float|null, draft: bool}|null
     */
    private function overall_cell_for_slot(array $student, int $slot_index): ?array
    {
        $reviewer_user_id = $this->reviewer_user_id_for_slot($student, $slot_index);
        if ($reviewer_user_id <= 0) {
            return null;
        }

        foreach ($student['panel_reviewers'] ?? [] as $row) {
            if ((int) ($row['slot_index'] ?? -1) !== $slot_index) {
                continue;
            }
            $total = $row['total'] ?? null;
            if ($total === null) {
                return null;
            }
            if (is_array($total)) {
                return [
                    'score' => $total['score'] ?? null,
                    'draft' => (bool) ($total['draft'] ?? false),
                ];
            }

            return [
                'score' => is_numeric($total) ? (float) $total : null,
                'draft' => false,
            ];
        }

        $legacy = $student['reviewer_totals'][(string) $reviewer_user_id]
            ?? $student['reviewer_totals'][$reviewer_user_id]
            ?? null;
        if ($legacy === null) {
            return null;
        }
        if (is_array($legacy)) {
            return [
                'score' => $legacy['score'] ?? null,
                'draft' => (bool) ($legacy['draft'] ?? false),
            ];
        }

        return [
            'score' => is_numeric($legacy) ? (float) $legacy : null,
            'draft' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{
     *   reviewer_slots: list<array{key: string}>,
     *   groups: list<array{leaves: list<array{key: string}>}>
     * } $columns
     * @return list<array<string, mixed>>
     */
    private function sort_scores_export_rows(array $rows, array $columns, string $sort_key, string $sort_dir): array
    {
        return $this->sort_export_rows($rows, ['groups' => $columns['groups']], $sort_key, $sort_dir);
    }

    /**
     * @param array{
     *   reviewer_slots: list<array{key: string, label: string}>,
     *   groups: list<array{id: string, label: string, leaves: list<array{key: string, label: string}>>
     * } $columns
     * @param list<array<string, mixed>> $sorted_rows
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row: int, header_row_count: int, numeric_columns: list<int>}
     * }
     */
    private function build_scores_export_sheet(array $columns, array $sorted_rows): array
    {
        $reviewer_slots = $columns['reviewer_slots'] ?? [];
        $leaves = [];
        $colspans = [];
        foreach ($columns['groups'] as $group) {
            $colspans[] = max(1, count($group['leaves']));
            foreach ($group['leaves'] as $leaf) {
                $leaves[] = $leaf;
            }
        }

        $fixed_count = 5;
        $header1 = [
            'Reg no',
            'Student',
            'Panel',
            'Panel coordinator',
            'Reviewers',
        ];
        foreach ($columns['groups'] as $group) {
            $header1[] = (string) ($group['label'] ?? '');
            for ($i = 1, $span = count($group['leaves']); $i < $span; $i++) {
                $header1[] = '';
            }
        }
        $header1[] = 'Weighted review score';

        $header2 = array_fill(0, $fixed_count, '');
        foreach ($leaves as $leaf) {
            $header2[] = (string) ($leaf['label'] ?? '');
        }
        $header2[] = '';

        $sheet_rows = [$header1, $header2];
        foreach ($sorted_rows as $row) {
            $line = [
                (string) ($row['reg_no'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['panel_name'] ?? ''),
                (string) ($row['panel_coordinator_name'] ?? ''),
                (string) ($row['panel_reviewer_names'] ?? ''),
            ];
            foreach ($leaves as $leaf) {
                $cell = $row['cells'][$leaf['key']] ?? null;
                $line[] = ($cell !== null && $cell['score'] !== null) ? $cell['score'] : '';
            }
            $line[] = $row['review_score'];
            $sheet_rows[] = $line;
        }

        $merge_plan = [];
        for ($col = 0; $col < $fixed_count; $col++) {
            $merge_plan[] = ['col' => $col, 'start_row' => 1, 'end_row' => 2];
        }
        $merge_plan = array_merge(
            $merge_plan,
            ExportService::merge_plan_for_row_groups(1, $fixed_count, $colspans)
        );
        $merge_plan[] = [
            'col' => count($header1) - 1,
            'start_row' => 1,
            'end_row' => 2,
        ];

        $numeric_columns = [count($header1) - 1];
        $score_col = $fixed_count;
        foreach ($leaves as $_leaf) {
            $numeric_columns[] = $score_col;
            $score_col++;
        }

        return [
            'rows' => $sheet_rows,
            'merge_plan' => $merge_plan,
            'styles' => [
                'freeze_row' => 2,
                'header_row_count' => 2,
                'numeric_columns' => $numeric_columns,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $criteria
     * @return array{
     *   reviewer_slots: list<array{key: string, label: string, slot_index: int}>,
     *   groups: list<array{id: string, label: string, leaves: list<array{key: string, label: string, criterion_id: int, slot_index: int}>>
     * }
     */
    private function build_export_columns(string $layout, array $criteria, int $max_panel_reviewer_slots): array
    {
        $reviewer_slots = [];
        for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
            $reviewer_slots[] = [
                'key' => 'reviewer_slot_' . $slot,
                'label' => sprintf(
                    /* translators: %d: reviewer slot number (1-based) */
                    __('Reviewer %d', 'project-reviews'),
                    $slot + 1
                ),
                'slot_index' => $slot,
            ];
        }

        $groups = [];
        if ($layout === 'reviewer') {
            for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
                $leaves = [];
                foreach ($criteria as $criterion) {
                    $criterion_id = (int) ($criterion['id'] ?? 0);
                    $leaves[] = [
                        'key' => $this->slot_leaf_key($criterion_id, $slot),
                        'label' => (string) ($criterion['label'] ?? ''),
                        'criterion_id' => $criterion_id,
                        'slot_index' => $slot,
                    ];
                }
                $groups[] = [
                    'id' => 'reviewer-slot-' . $slot,
                    'label' => sprintf(
                        /* translators: %d: reviewer slot number (1-based) */
                        __('Reviewer %d', 'project-reviews'),
                        $slot + 1
                    ),
                    'leaves' => $leaves,
                ];
            }
        } else {
            foreach ($criteria as $criterion) {
                $criterion_id = (int) ($criterion['id'] ?? 0);
                $leaves = [];
                for ($slot = 0; $slot < $max_panel_reviewer_slots; $slot++) {
                    $leaves[] = [
                        'key' => $this->slot_leaf_key($criterion_id, $slot),
                        'label' => sprintf(
                            /* translators: %d: reviewer slot number (1-based) */
                            __('Reviewer %d', 'project-reviews'),
                            $slot + 1
                        ),
                        'criterion_id' => $criterion_id,
                        'slot_index' => $slot,
                    ];
                }
                $groups[] = [
                    'id' => 'criterion-' . $criterion_id,
                    'label' => (string) ($criterion['label'] ?? ''),
                    'leaves' => $leaves,
                ];
            }
        }

        return [
            'reviewer_slots' => $reviewer_slots,
            'groups' => $groups,
        ];
    }

    private function slot_leaf_key(int $criterion_id, int $slot_index): string
    {
        return 'c' . $criterion_id . '_s' . $slot_index;
    }

    /**
     * @param list<array<string, mixed>> $mark_students
     * @param list<array<string, mixed>> $score_students
     * @param array{
     *   reviewer_slots: list<array{key: string, slot_index: int}>,
     *   groups: list<array{leaves: list<array{key: string, criterion_id: int, slot_index: int}>>
     * } $columns
     * @return list<array<string, mixed>>
     */
    private function build_export_rows(
        array $mark_students,
        array $score_students,
        array $columns,
        int $max_panel_reviewer_slots
    ): array {
        $review_scores = [];
        foreach ($score_students as $student) {
            $review_scores[(int) ($student['student_id'] ?? 0)] = $student['review_score'] ?? null;
        }

        $leaf_slots = [];
        foreach ($columns['groups'] as $group) {
            foreach ($group['leaves'] as $leaf) {
                $leaf_slots[(string) $leaf['key']] = [
                    'criterion_id' => (int) ($leaf['criterion_id'] ?? 0),
                    'slot_index' => (int) ($leaf['slot_index'] ?? 0),
                ];
            }
        }

        $rows = [];
        foreach ($mark_students as $student) {
            $cells = [];
            foreach ($leaf_slots as $key => $meta) {
                $cells[$key] = $this->score_cell_for_slot(
                    $student,
                    $meta['criterion_id'],
                    $meta['slot_index']
                );
            }

            $student_id = (int) ($student['student_id'] ?? 0);
            $rows[] = [
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'panel_name' => (string) ($student['panel_name'] ?? ''),
                'panel_coordinator_name' => (string) ($student['panel_coordinator_name'] ?? ''),
                'panel_reviewer_names' => (string) ($student['panel_reviewer_names'] ?? ''),
                'attendance_status' => (string) ($student['attendance_status'] ?? ReviewAssignmentRepository::ATTENDANCE_PRESENT),
                'mark_status' => (string) ($student['mark_status'] ?? 'not_started'),
                'cells' => $cells,
                'review_score' => $review_scores[$student_id] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $student
     * @return array{score: float|null, draft: bool, reviewer_name?: string}|null
     */
    private function score_cell_for_slot(array $student, int $criterion_id, int $slot_index): ?array
    {
        $reviewer_user_id = $this->reviewer_user_id_for_slot($student, $slot_index);
        if ($reviewer_user_id <= 0) {
            return null;
        }

        return $this->score_cell_from_entry($student, $criterion_id, $reviewer_user_id);
    }

    /**
     * @param array<string, mixed> $student
     */
    private function reviewer_user_id_for_slot(array $student, int $slot_index): int
    {
        foreach ($student['panel_reviewers'] ?? [] as $row) {
            if ((int) ($row['slot_index'] ?? -1) === $slot_index) {
                return (int) ($row['user_id'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $student
     */
    private function reviewer_name_for_slot(array $student, int $slot_index): string
    {
        foreach ($student['panel_reviewers'] ?? [] as $row) {
            if ((int) ($row['slot_index'] ?? -1) === $slot_index) {
                return trim((string) ($row['name'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $student
     * @return array{score: float|null, draft: bool}|null
     */
    private function score_cell_from_entry(array $student, int $criterion_id, int $reviewer_user_id): ?array
    {
        if (($student['attendance_status'] ?? '') === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            return null;
        }

        $entries = $student['marks'][(string) $criterion_id] ?? $student['marks'][$criterion_id] ?? [];

        foreach ($entries as $entry) {
            if ((int) ($entry['reviewer_user_id'] ?? 0) !== $reviewer_user_id) {
                continue;
            }
            if ($entry['score'] === null) {
                return null;
            }

            $is_draft = (string) ($entry['status'] ?? '') !== MarkRepository::STATUS_SUBMITTED;

            return [
                'score' => (float) $entry['score'],
                'draft' => $is_draft,
            ];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{groups: list<array{leaves: list<array{key: string}>}>} $columns
     * @return list<array<string, mixed>>
     */
    private function sort_export_rows(array $rows, array $columns, string $sort_key, string $sort_dir): array
    {
        $leaf_keys = [];
        foreach ($columns['groups'] as $group) {
            foreach ($group['leaves'] as $leaf) {
                $leaf_keys[(string) $leaf['key']] = true;
            }
        }

        usort(
            $rows,
            function (array $a, array $b) use ($sort_key, $sort_dir, $leaf_keys): int {
                $value_a = $this->sort_value_for_row($a, $sort_key, $leaf_keys);
                $value_b = $this->sort_value_for_row($b, $sort_key, $leaf_keys);
                $cmp = $this->compare_sort_values($value_a, $value_b, $sort_dir);
                if ($cmp !== 0) {
                    return $sort_dir === 'desc' ? -$cmp : $cmp;
                }

                return strcmp((string) ($a['reg_no'] ?? ''), (string) ($b['reg_no'] ?? ''));
            }
        );

        return $rows;
    }

    /**
     * @param array<string, bool> $leaf_keys
     */
    private function sort_value_for_row(array $row, string $sort_key, array $leaf_keys): mixed
    {
        if ($sort_key === 'reg_no') {
            return $row['reg_no'] ?? '';
        }
        if ($sort_key === 'name') {
            return $row['name'] ?? '';
        }
        if ($sort_key === 'attendance') {
            return $row['attendance_status'] ?? '';
        }
        if ($sort_key === 'mark_status') {
            return $row['mark_status'] ?? '';
        }
        if ($sort_key === 'panel') {
            return $row['panel_name'] ?? '';
        }
        if ($sort_key === 'panel_coordinator') {
            return $row['panel_coordinator_name'] ?? '';
        }
        if ($sort_key === 'reviewers') {
            return $row['panel_reviewer_names'] ?? '';
        }
        if ($sort_key === 'review_score') {
            return $row['review_score'] ?? null;
        }
        if (!isset($leaf_keys[$sort_key])) {
            return null;
        }

        $cell = $row['cells'][$sort_key] ?? null;
        if ($cell === null) {
            return null;
        }

        return $cell['score'] ?? null;
    }

    private function compare_sort_values(mixed $a, mixed $b, string $sort_dir): int
    {
        $a_missing = $a === null || $a === '';
        $b_missing = $b === null || $b === '';
        if ($a_missing && $b_missing) {
            return 0;
        }
        if ($a_missing) {
            return $sort_dir === 'asc' ? 1 : -1;
        }
        if ($b_missing) {
            return $sort_dir === 'asc' ? -1 : 1;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }

        return strcasecmp((string) $a, (string) $b);
    }

    /**
     * @param array{groups: list<array{id: string, label: string, leaves: list<array{key: string, label: string}>}>} $columns
     * @param list<array<string, mixed>> $sorted_rows
     * @return array{
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array<string, int>>,
     *   styles: array{freeze_row: int, header_row_count: int, numeric_columns: list<int>}
     * }
     */
    private function build_marks_export_sheet(array $columns, array $sorted_rows): array
    {
        $reviewer_slots = $columns['reviewer_slots'] ?? [];
        $leaves = [];
        $colspans = [];
        foreach ($columns['groups'] as $group) {
            $colspans[] = max(1, count($group['leaves']));
            foreach ($group['leaves'] as $leaf) {
                $leaves[] = $leaf;
            }
        }

        $fixed_count = 7;
        $header1 = [
            'Reg no',
            'Student',
            'Panel',
            'Panel coordinator',
            'Reviewers',
            'Attendance',
            'Status',
        ];
        foreach ($columns['groups'] as $group) {
            $header1[] = (string) ($group['label'] ?? '');
            for ($i = 1, $span = count($group['leaves']); $i < $span; $i++) {
                $header1[] = '';
            }
        }
        $header1[] = 'Weighted review score';

        $header2 = array_fill(0, $fixed_count, '');
        foreach ($leaves as $leaf) {
            $header2[] = (string) ($leaf['label'] ?? '');
        }
        $header2[] = '';

        $sheet_rows = [$header1, $header2];
        foreach ($sorted_rows as $row) {
            $line = [
                (string) ($row['reg_no'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['panel_name'] ?? ''),
                (string) ($row['panel_coordinator_name'] ?? ''),
                (string) ($row['panel_reviewer_names'] ?? ''),
                $this->format_attendance_export((string) ($row['attendance_status'] ?? '')),
                $this->format_mark_status_export((string) ($row['mark_status'] ?? '')),
            ];
            foreach ($leaves as $leaf) {
                $cell = $row['cells'][$leaf['key']] ?? null;
                $line[] = ($cell !== null && $cell['score'] !== null) ? $cell['score'] : '';
            }
            $line[] = $row['review_score'];
            $sheet_rows[] = $line;
        }

        $merge_plan = [];
        for ($col = 0; $col < $fixed_count; $col++) {
            $merge_plan[] = ['col' => $col, 'start_row' => 1, 'end_row' => 2];
        }
        $merge_plan = array_merge(
            $merge_plan,
            ExportService::merge_plan_for_row_groups(1, $fixed_count, $colspans)
        );

        $numeric_columns = [count($header1) - 1];
        $score_col = $fixed_count;
        foreach ($leaves as $_leaf) {
            $numeric_columns[] = $score_col;
            $score_col++;
        }

        return [
            'rows' => $sheet_rows,
            'merge_plan' => $merge_plan,
            'styles' => [
                'freeze_row' => 2,
                'header_row_count' => 2,
                'numeric_columns' => $numeric_columns,
            ],
        ];
    }

    /**
     * @return list<array{user_id: int, name: string}>
     */
    private function list_reviewers_for_review(int $review_id): array
    {
        $reviewers = [];
        $seen = [];
        foreach ($this->assignments->list_panel_reviewers($review_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0 || isset($seen[$user_id])) {
                continue;
            }
            $seen[$user_id] = true;
            $reviewers[] = [
                'user_id' => $user_id,
                'name' => $this->reviewer_display_name($review_id, $user_id),
            ];
        }

        usort(
            $reviewers,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return $reviewers;
    }

    /**
     * Aggregate mark status for one student across all panel reviewers.
     *
     * @param list<array{user_id: int, slot_index?: int}> $panel_reviewers
     * @param list<array<string, mixed>> $criteria
     */
    private function student_report_mark_status(
        int $session_id,
        int $review_id,
        int $student_id,
        array $panel_reviewers,
        array $criteria,
        bool $coordinator_locked
    ): string {
        if ($coordinator_locked) {
            return 'locked';
        }

        $criteria_count = count($criteria);
        if ($criteria_count <= 0 || $panel_reviewers === []) {
            return 'not_started';
        }

        $mark_service = new MarkService(
            $this->sessions,
            $this->reviews,
            $this->assignments,
            $this->marks
        );

        $has_activity = false;
        $all_frozen = true;
        $all_complete = true;
        $linked_reviewer_count = 0;

        foreach ($panel_reviewers as $reviewer) {
            $user_id = (int) ($reviewer['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            ++$linked_reviewer_count;

            $mark_rows = $this->marks->list_for_student_review(
                $session_id,
                $review_id,
                $student_id,
                $user_id
            );
            if ($mark_rows !== []) {
                $has_activity = true;
            }

            if (!$this->marks->is_student_frozen_for_reviewer(
                $session_id,
                $review_id,
                $student_id,
                $user_id,
                $criteria_count
            )) {
                $all_frozen = false;
            }

            if (!$mark_service->is_student_marking_complete(
                $session_id,
                $review_id,
                $student_id,
                $user_id,
                $criteria
            )) {
                $all_complete = false;
            }
        }

        if ($linked_reviewer_count === 0) {
            return 'not_started';
        }

        if ($all_frozen && $has_activity) {
            return 'frozen';
        }

        if (!$has_activity) {
            return 'not_started';
        }

        if ($all_complete) {
            return 'in_progress';
        }

        return 'draft';
    }

    private function format_attendance_export(string $status): string
    {
        return $status === ReviewAssignmentRepository::ATTENDANCE_ABSENT ? 'Absent' : 'Present';
    }

    private function format_panel_roster_attendance(string $status): string
    {
        if ($status === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            return 'Absent';
        }
        if ($status === ReviewAssignmentRepository::ATTENDANCE_PRESENT) {
            return 'Present';
        }

        return '';
    }

    private function format_mark_status_export(string $status): string
    {
        return match ($status) {
            'locked' => 'Locked',
            'frozen' => 'Frozen',
            'in_progress' => 'In progress',
            'draft' => 'Draft',
            default => 'Not started',
        };
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function require_review(int $session_id, int $review_id): array|\WP_Error
    {
        if ($this->sessions->find_by_id($session_id) === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $review = $this->reviews->find_by_id($review_id);
        if ($review === null || (int) ($review['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error('pr_review_not_found', __('Review not found.', 'project-reviews'), ['status' => 404]);
        }

        return $review;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function require_confirmed_review(int $session_id, int $review_id): array|\WP_Error
    {
        $review = $this->require_review($session_id, $review_id);
        if ($review instanceof \WP_Error) {
            return $review;
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            return new \WP_Error(
                'rubric_not_confirmed',
                __('The rubric for this review is not confirmed yet.', 'project-reviews'),
                ['status' => 403]
            );
        }

        return $review;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list_enrolled_students(int $session_id): array
    {
        $lines = [];
        foreach ($this->sessions->list_enrolled($session_id) as $enrol) {
            $student = $this->students->find_by_id((int) ($enrol['student_id'] ?? 0));
            if ($student === null) {
                continue;
            }
            $lines[] = array_merge($student, [
                'guide_name' => trim((string) ($enrol['guide_name'] ?? '')),
                'guide_emp_id' => trim((string) ($enrol['guide_emp_id'] ?? '')),
                'project_title' => trim((string) ($enrol['project_title'] ?? '')),
            ]);
        }

        usort(
            $lines,
            static fn (array $a, array $b): int => strcmp((string) ($a['reg_no'] ?? ''), (string) ($b['reg_no'] ?? ''))
        );

        return $lines;
    }

    private function compute_max_panel_reviewer_slots(int $session_id, int $review_id): int
    {
        $max_slots = 0;
        foreach ($this->panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id <= 0) {
                continue;
            }
            $count = count($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id));
            $max_slots = max($max_slots, $count);
        }

        return $max_slots;
    }

    /**
     * @return array<int, int|null> student_id => panel_id
     */
    private function panel_id_map_for_review(int $session_id, int $review_id): array
    {
        $map = [];
        foreach ($this->assignments->list_student_panels($review_id) as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($student_id > 0 && $panel_id > 0) {
                $map[$student_id] = $panel_id;
            }
        }

        foreach ($this->sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            if ($student_id <= 0 || isset($map[$student_id])) {
                continue;
            }
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $map[$student_id] = $panel_id;
            }
        }

        return $map;
    }

    /**
     * @param list<array{user_id: int, name: string, slot_index?: int}> $panel_reviewers
     * @return array{panel_name: string, panel_coordinator_name: string, panel_reviewer_names: string}
     */
    private function panel_context_for_student(int $review_id, ?int $panel_id, array $panel_reviewers): array
    {
        if ($panel_id === null || $panel_id <= 0) {
            return [
                'panel_name' => '',
                'panel_coordinator_name' => '',
                'panel_reviewer_names' => '',
            ];
        }

        $panel = $this->panels->find_by_id($panel_id);
        $coordinator_name = '';
        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            if (empty($row['is_panel_head'])) {
                continue;
            }
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id > 0) {
                $coordinator_name = $this->reviewer_display_name($review_id, $user_id);
                break;
            }
        }

        $names = [];
        foreach ($panel_reviewers as $reviewer) {
            $name = trim((string) ($reviewer['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return [
            'panel_name' => $panel !== null ? (string) ($panel['name'] ?? '') : '',
            'panel_coordinator_name' => $coordinator_name,
            'panel_reviewer_names' => implode(', ', $names),
        ];
    }

    /**
     * @return list<array{user_id: int, name: string, slot_index: int}>
     */
    private function build_panel_reviewers_payload(int $review_id, ?int $panel_id): array
    {
        if ($panel_id === null || $panel_id <= 0) {
            return [];
        }

        $payload = [];
        foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $index => $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $payload[] = [
                'user_id' => $user_id,
                'name' => $this->reviewer_display_name($review_id, $user_id),
                'slot_index' => $index,
            ];
        }

        return $payload;
    }

    /**
     * @return array{score: float, draft: bool}|null
     */
    private function reviewer_total_cell_for_panel_report(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        int $criteria_count
    ): ?array {
        if ($criteria_count <= 0 || $reviewer_user_id <= 0) {
            return null;
        }

        if ($this->assignments->get_attendance_status($review_id, $student_id)
            === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
            return null;
        }

        $frozen = $this->marks->is_student_frozen_for_reviewer(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            $criteria_count
        );
        if ($frozen) {
            return [
                'score' => $this->scores->calculate_reviewer_total(
                    $session_id,
                    $student_id,
                    $review_id,
                    $reviewer_user_id,
                    true
                ),
                'draft' => false,
            ];
        }

        $mark_rows = $this->marks->list_for_student_review(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id
        );
        if ($mark_rows === []) {
            return null;
        }

        return [
            'score' => $this->scores->calculate_reviewer_total(
                $session_id,
                $student_id,
                $review_id,
                $reviewer_user_id,
                false
            ),
            'draft' => true,
        ];
    }

    private function reviewer_display_name(int $review_id, int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        foreach ($this->assignments->list_panel_reviewers($review_id) as $row) {
            if ((int) ($row['user_id'] ?? 0) !== $user_id) {
                continue;
            }
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($panel_id <= 0) {
                continue;
            }
            foreach ($this->panels->list_reviewers($panel_id) as $session_reviewer) {
                if ((int) ($session_reviewer['user_id'] ?? 0) !== $user_id) {
                    continue;
                }
                $name = trim((string) ($session_reviewer['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        if (function_exists('get_userdata')) {
            $user = get_userdata($user_id);
            if ($user !== null && !empty($user->display_name)) {
                return (string) $user->display_name;
            }
        }

        return sprintf(/* translators: %d: reviewer user id */ __('Reviewer %d', 'project-reviews'), $user_id);
    }
}
