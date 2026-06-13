<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class ReportQueryService
{
    public const TYPE_STUDENT_MASTER = 'student_master';

    public const TYPE_MARKS_DETAIL = 'marks_detail';

    public const TYPE_REVIEW_SUMMARY = 'review_summary';

    public const TYPE_COMBINED_SCORES = 'combined_scores';

    public const TYPE_PANEL_PROGRESS = 'panel_progress';

    public const TYPE_AUDIT_LOG = 'audit_log';

    public const TYPE_RUBRIC_SCORES = 'rubric_scores';

    /**
     * Legacy session-scoped report types (Story 7.3). Internal/scripts/tests only —
     * not exposed in coordinator catalog; REST download returns 410 Gone (Story 12.5).
     *
     * @var list<string>
     */
    public const ALL_TYPES = [
        self::TYPE_STUDENT_MASTER,
        self::TYPE_MARKS_DETAIL,
        self::TYPE_REVIEW_SUMMARY,
        self::TYPE_COMBINED_SCORES,
        self::TYPE_PANEL_PROGRESS,
        self::TYPE_AUDIT_LOG,
        self::TYPE_RUBRIC_SCORES,
    ];

    private object $wpdb;

    private ScoreService $scores;

    public function __construct(?object $wpdb = null, ?ScoreService $scores = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('ReportQueryService requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->scores = $scores ?? new ScoreService();
    }

    /**
     * @return array{
     *   type: string,
     *   rows: list<list<string|int|float|null>>,
     *   merge_plan: list<array{col: int, start_row: int, end_row: int}>,
     *   styles: array{freeze_row?: int, numeric_columns?: list<int>}
     * }
     */
    public function build(string $type, int $session_id): array
    {
        if (!in_array($type, self::ALL_TYPES, true)) {
            throw new \InvalidArgumentException('Unknown report type: ' . $type);
        }

        return match ($type) {
            self::TYPE_STUDENT_MASTER => $this->student_master($session_id),
            self::TYPE_MARKS_DETAIL => $this->marks_detail($session_id),
            self::TYPE_REVIEW_SUMMARY => $this->review_summary($session_id),
            self::TYPE_COMBINED_SCORES => $this->combined_scores($session_id),
            self::TYPE_PANEL_PROGRESS => $this->panel_progress($session_id),
            self::TYPE_AUDIT_LOG => $this->audit_log($session_id),
            self::TYPE_RUBRIC_SCORES => $this->rubric_scores($session_id),
        };
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function student_master(int $session_id): array
    {
        $header = ['Panel', 'Reg No', 'Name', 'Program', 'Batch', 'Guide'];
        $rows = [$header];

        $enrol_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pr_session_students WHERE session_id = %d",
            $session_id
        );
        $enrolments = $this->wpdb->get_results($enrol_sql, 'ARRAY_A');
        $lines = [];
        foreach (is_array($enrolments) ? $enrolments : [] as $enrol) {
            $student = $this->find_row($this->wpdb->prefix . 'pr_students', (int) ($enrol['student_id'] ?? 0));
            if ($student === null) {
                continue;
            }
            $panel_name = '';
            $panel_id = (int) ($enrol['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $panel = $this->find_row($this->wpdb->prefix . 'pr_panels', $panel_id);
                $panel_name = (string) ($panel['name'] ?? '');
            }
            $lines[] = [
                'panel_name' => $panel_name,
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'program' => (string) ($student['program'] ?? ''),
                'batch' => (string) ($student['batch'] ?? ''),
                'guide_name' => trim((string) ($enrol['guide_name'] ?? '')),
            ];
        }
        usort(
            $lines,
            static fn (array $a, array $b): int => [$a['panel_name'], $a['reg_no']] <=> [$b['panel_name'], $b['reg_no']]
        );
        foreach ($lines as $line) {
            $rows[] = [
                $line['panel_name'],
                $line['reg_no'],
                $line['name'],
                $line['program'],
                $line['batch'],
                $line['guide_name'],
            ];
        }

        $merge_plan = ExportService::merge_plan_for_columns($rows, [0]);

        return [
            'type' => self::TYPE_STUDENT_MASTER,
            'rows' => $rows,
            'merge_plan' => $merge_plan,
            'styles' => ['freeze_row' => 1],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function marks_detail(int $session_id): array
    {
        $header = [
            'Panel',
            'Student',
            'Review',
            'Criterion',
            'Reviewer',
            'Score',
            'Status',
            'Flagged',
            'Coordinator override',
            'Previous score',
        ];
        $rows = [$header];

        $marks_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pr_marks WHERE session_id = %d",
            $session_id
        );
        $marks = $this->wpdb->get_results($marks_sql, 'ARRAY_A');
        $lines = [];
        foreach (is_array($marks) ? $marks : [] as $mark) {
            $student = $this->find_row($this->wpdb->prefix . 'pr_students', (int) ($mark['student_id'] ?? 0));
            $review = $this->find_row($this->wpdb->prefix . 'pr_reviews', (int) ($mark['review_id'] ?? 0));
            $criterion = $this->find_row($this->wpdb->prefix . 'pr_rubric_criteria', (int) ($mark['criterion_id'] ?? 0));
            $panel_name = $this->panel_name_for_student($session_id, (int) ($mark['student_id'] ?? 0));
            $reviewer_name = $this->reviewer_display_name((int) ($mark['reviewer_user_id'] ?? 0));
            $student_label = $student === null
                ? ''
                : (string) (($student['reg_no'] ?? '') . ' — ' . ($student['name'] ?? ''));
            $lines[] = [
                'panel_name' => $panel_name,
                'student_label' => $student_label,
                'review_label' => (string) ($review['label'] ?? ''),
                'criterion_label' => (string) ($criterion['label'] ?? ''),
                'reviewer_name' => $reviewer_name,
                'score' => $mark['score'] !== null ? (float) $mark['score'] : null,
                'status' => (string) ($mark['status'] ?? ''),
                'flagged' => !empty($mark['flagged']) ? 'Yes' : 'No',
                'coordinator_override' => !empty($mark['coordinator_overridden']) ? 'Yes' : 'No',
                'previous_score' => !empty($mark['coordinator_overridden']) && $mark['overridden_from_score'] !== null && $mark['overridden_from_score'] !== ''
                    ? (float) $mark['overridden_from_score']
                    : null,
            ];
        }
        usort(
            $lines,
            static fn (array $a, array $b): int => [
                $a['panel_name'],
                $a['student_label'],
                $a['review_label'],
                $a['criterion_label'],
                $a['reviewer_name'],
            ] <=> [
                $b['panel_name'],
                $b['student_label'],
                $b['review_label'],
                $b['criterion_label'],
                $b['reviewer_name'],
            ]
        );
        foreach ($lines as $line) {
            $rows[] = [
                $line['panel_name'],
                $line['student_label'],
                $line['review_label'],
                $line['criterion_label'],
                $line['reviewer_name'],
                $line['score'],
                $line['status'],
                $line['flagged'],
                $line['coordinator_override'],
                $line['previous_score'],
            ];
        }

        $merge_plan = ExportService::merge_plan_for_columns($rows, [0, 1, 2]);

        return [
            'type' => self::TYPE_MARKS_DETAIL,
            'rows' => $rows,
            'merge_plan' => $merge_plan,
            'styles' => ['freeze_row' => 1, 'numeric_columns' => [5]],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function review_summary(int $session_id): array
    {
        $header = ['Review', 'Student', 'Review Score', 'Submitted Marks'];
        $rows = [$header];

        $reviews_sql = $this->wpdb->prepare(
            "SELECT id, label FROM {$this->wpdb->prefix}pr_reviews WHERE session_id = %d ORDER BY sort_order, id",
            $session_id
        );
        $reviews = $this->wpdb->get_results($reviews_sql, 'ARRAY_A');
        $students_sql = $this->wpdb->prepare(
            "SELECT s.id, s.reg_no, s.name
             FROM {$this->wpdb->prefix}pr_session_students ss
             INNER JOIN {$this->wpdb->prefix}pr_students s ON s.id = ss.student_id
             WHERE ss.session_id = %d ORDER BY s.reg_no",
            $session_id
        );
        $students = $this->wpdb->get_results($students_sql, 'ARRAY_A');

        foreach (is_array($reviews) ? $reviews : [] as $review) {
            foreach (is_array($students) ? $students : [] as $student) {
                $aggregate = $this->scores->calculate_student_review_aggregate(
                    $session_id,
                    (int) $student['id'],
                    (int) $review['id']
                );
                $submitted = $this->count_submitted_marks(
                    $session_id,
                    (int) $student['id'],
                    (int) $review['id']
                );
                $rows[] = [
                    (string) ($review['label'] ?? ''),
                    (string) (($student['reg_no'] ?? '') . ' — ' . ($student['name'] ?? '')),
                    $aggregate['review_score'],
                    $submitted,
                ];
            }
        }

        $merge_plan = ExportService::merge_plan_for_columns($rows, [0]);

        return [
            'type' => self::TYPE_REVIEW_SUMMARY,
            'rows' => $rows,
            'merge_plan' => $merge_plan,
            'styles' => ['freeze_row' => 1, 'numeric_columns' => [2]],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function combined_scores(int $session_id): array
    {
        $header = ['Panel', 'Student', 'Review', 'Combined Score'];
        $rows = [$header];

        $enrol_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pr_session_students WHERE session_id = %d",
            $session_id
        );
        $enrolments = $this->wpdb->get_results($enrol_sql, 'ARRAY_A');
        $reviews_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pr_reviews WHERE session_id = %d",
            $session_id
        );
        $reviews = $this->wpdb->get_results($reviews_sql, 'ARRAY_A');
        $lines = [];
        foreach (is_array($enrolments) ? $enrolments : [] as $enrol) {
            $student = $this->find_row($this->wpdb->prefix . 'pr_students', (int) ($enrol['student_id'] ?? 0));
            if ($student === null) {
                continue;
            }
            $panel_name = $this->panel_name_for_student($session_id, (int) ($enrol['student_id'] ?? 0));
            foreach (is_array($reviews) ? $reviews : [] as $review) {
                $aggregate = $this->scores->calculate_student_review_aggregate(
                    $session_id,
                    (int) ($student['id'] ?? 0),
                    (int) ($review['id'] ?? 0)
                );
                $lines[] = [
                    'panel_name' => $panel_name,
                    'student_label' => (string) (($student['reg_no'] ?? '') . ' — ' . ($student['name'] ?? '')),
                    'review_label' => (string) ($review['label'] ?? ''),
                    'combined_score' => $aggregate['combined_score'],
                ];
            }
        }
        usort(
            $lines,
            static fn (array $a, array $b): int => [
                $a['panel_name'],
                $a['student_label'],
                $a['review_label'],
            ] <=> [$b['panel_name'], $b['student_label'], $b['review_label']]
        );
        foreach ($lines as $line) {
            $rows[] = [
                $line['panel_name'],
                $line['student_label'],
                $line['review_label'],
                $line['combined_score'],
            ];
        }

        $merge_plan = ExportService::merge_plan_for_columns($rows, [0, 1]);

        return [
            'type' => self::TYPE_COMBINED_SCORES,
            'rows' => $rows,
            'merge_plan' => $merge_plan,
            'styles' => ['freeze_row' => 1, 'numeric_columns' => [3]],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function panel_progress(int $session_id): array
    {
        $header = ['Review', 'Panel', 'Reviewer', 'Status', 'Completed', 'Total', 'Percent'];
        $rows = [$header];

        $progress = new ScoreService($this->wpdb);
        foreach ($progress->calculate_session_progress($session_id) as $review) {
            foreach ($review['rows'] ?? [] as $row) {
                $rows[] = [
                    (string) ($review['review_label'] ?? ''),
                    (string) ($row['panel_name'] ?? ''),
                    (string) ($row['reviewer_name'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (int) ($row['completed'] ?? 0),
                    (int) ($row['total'] ?? 0),
                    (float) ($row['percent'] ?? 0),
                ];
            }
        }

        $merge_plan = ExportService::merge_plan_for_columns($rows, [0, 1]);

        return [
            'type' => self::TYPE_PANEL_PROGRESS,
            'rows' => $rows,
            'merge_plan' => $merge_plan,
            'styles' => ['freeze_row' => 1, 'numeric_columns' => [4, 5, 6]],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function audit_log(int $session_id): array
    {
        $header = ['Timestamp', 'Actor', 'Action', 'Entity', 'Entity ID', 'Old Value', 'New Value'];
        $rows = [$header];

        $audit = new AuditService($this->wpdb);
        $entries = $audit->list_for_session($session_id, 1, 5000);
        foreach ($entries['items'] as $entry) {
            $rows[] = [
                (string) ($entry['created_at'] ?? ''),
                (string) ($entry['actor_name'] ?? ''),
                (string) ($entry['action'] ?? ''),
                (string) ($entry['entity'] ?? ''),
                (int) ($entry['entity_id'] ?? 0),
                (string) ($entry['old_value'] ?? ''),
                (string) ($entry['new_value'] ?? ''),
            ];
        }

        return [
            'type' => self::TYPE_AUDIT_LOG,
            'rows' => $rows,
            'merge_plan' => [],
            'styles' => ['freeze_row' => 1],
        ];
    }

    /**
     * @return array{type: string, rows: list<list<string|int|float|null>>, merge_plan: list<array{col: int, start_row: int, end_row: int}>, styles: array{freeze_row?: int, numeric_columns?: list<int>}}
     */
    private function rubric_scores(int $session_id): array
    {
        $header = [
            'Project ID',
            'Review ID',
            'Reg No',
            'Reviewer ID',
            'Rubric ID',
            'Score',
            'Status',
            'Flagged',
            'Coordinator override',
            'Previous score',
        ];
        $rows = [$header];

        $view = $this->wpdb->prefix . 'pr_rubric_scores';
        $sql = $this->wpdb->prepare(
            "SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score, status, flagged,
                    coordinator_overridden, overridden_from_score
             FROM {$view}
             WHERE project_id = %d
             ORDER BY review_id, reg_no, reviewer_id, rubric_id",
            $session_id
        );
        $results = $this->wpdb->get_results($sql, 'ARRAY_A');
        foreach (is_array($results) ? $results : [] as $row) {
            $rows[] = [
                (int) ($row['project_id'] ?? 0),
                (int) ($row['review_id'] ?? 0),
                (string) ($row['reg_no'] ?? ''),
                (int) ($row['reviewer_id'] ?? 0),
                (int) ($row['rubric_id'] ?? 0),
                $row['score'] !== null ? (float) $row['score'] : null,
                (string) ($row['status'] ?? ''),
                !empty($row['flagged']) ? 'Yes' : 'No',
                !empty($row['coordinator_overridden']) ? 'Yes' : 'No',
                $row['overridden_from_score'] !== null && $row['overridden_from_score'] !== ''
                    ? (float) $row['overridden_from_score']
                    : null,
            ];
        }

        return [
            'type' => self::TYPE_RUBRIC_SCORES,
            'rows' => $rows,
            'merge_plan' => [],
            'styles' => ['freeze_row' => 1, 'numeric_columns' => [5]],
        ];
    }

    private function count_submitted_marks(int $session_id, int $student_id, int $review_id): int
    {
        $table = $this->wpdb->prefix . 'pr_marks';
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE session_id = %d AND student_id = %d AND review_id = %d AND status = 'submitted'",
            $session_id,
            $student_id,
            $review_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    private function count_enrolled_students(int $session_id): int
    {
        $table = $this->wpdb->prefix . 'pr_session_students';
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE session_id = %d",
            $session_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    private function count_session_criteria(int $session_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pr_rubric_criteria c
             INNER JOIN {$this->wpdb->prefix}pr_reviews r ON r.id = c.review_id
             WHERE r.session_id = %d",
            $session_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    private function count_reviewer_submitted_marks(int $session_id, int $reviewer_user_id, int $panel_id): int
    {
        if ($reviewer_user_id <= 0) {
            return 0;
        }

        $table = $this->wpdb->prefix . 'pr_marks';
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} m
             INNER JOIN {$this->wpdb->prefix}pr_session_students ss
               ON ss.session_id = m.session_id AND ss.student_id = m.student_id
             WHERE m.session_id = %d AND m.reviewer_user_id = %d
               AND ss.panel_id = %d AND m.status = 'submitted'",
            $session_id,
            $reviewer_user_id,
            $panel_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_row(string $table, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    private function panel_name_for_student(int $session_id, int $student_id): string
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pr_session_students WHERE session_id = %d AND student_id = %d",
            $session_id,
            $student_id
        );
        $enrol = $this->wpdb->get_row($sql, 'ARRAY_A');
        if (!is_array($enrol)) {
            return '';
        }
        $panel = $this->find_row($this->wpdb->prefix . 'pr_panels', (int) ($enrol['panel_id'] ?? 0));

        return (string) ($panel['name'] ?? '');
    }

    private function reviewer_display_name(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        // Token-portal reviewers have no WordPress account; their identity
        // is the roster row, so the roster name must win over get_userdata.
        $roster_table = $this->wpdb->prefix . 'pr_panel_reviewers';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$roster_table} WHERE user_id = %d",
                $user_id
            ),
            'ARRAY_A'
        );
        if (is_array($row)) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        if (function_exists('get_userdata')) {
            $user = get_userdata($user_id);
            if ($user !== null && !empty($user->display_name)) {
                return (string) $user->display_name;
            }
        }

        return (string) $user_id;
    }
}
