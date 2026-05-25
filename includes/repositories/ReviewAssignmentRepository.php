<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class ReviewAssignmentRepository
{
    public const ATTENDANCE_PRESENT = 'present';

    public const ATTENDANCE_ABSENT = 'absent';

    private object $wpdb;

    private string $student_panels_table;

    private string $panel_reviewers_table;

    private string $reviews_table;

    private string $enrolment_table;

    private string $session_panel_reviewers_table;

    private string $attendance_by_reviewer_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('ReviewAssignmentRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $prefix = $this->wpdb->prefix;
        $this->student_panels_table = $prefix . 'pr_review_student_panels';
        $this->panel_reviewers_table = $prefix . 'pr_review_panel_reviewers';
        $this->reviews_table = $prefix . 'pr_reviews';
        $this->enrolment_table = $prefix . 'pr_session_students';
        $this->session_panel_reviewers_table = $prefix . 'pr_panel_reviewers';
        $this->attendance_by_reviewer_table = $prefix . 'pr_review_student_attendance_by_reviewer';
    }

    public function seed_from_session_defaults(int $review_id, int $session_id): void
    {
        $this->clear_review_assignments($review_id);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        foreach ($sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            $panel_id = (int) ($enrolment['panel_id'] ?? 0);
            if ($student_id <= 0 || $panel_id <= 0) {
                continue;
            }
            $title = trim((string) ($enrolment['project_title'] ?? ''));
            $this->set_student_panel(
                $review_id,
                $student_id,
                $panel_id,
                $title !== '' ? $title : null
            );
        }

        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id <= 0) {
                continue;
            }
            $panel_head = $panels->find_panel_head($panel_id);
            $panel_head_user_id = $panel_head !== null ? (int) ($panel_head['user_id'] ?? 0) : 0;
            foreach ($panels->list_reviewers($panel_id) as $reviewer) {
                $user_id = (int) ($reviewer['user_id'] ?? 0);
                if ($user_id <= 0) {
                    continue;
                }
                $is_head = $panel_head_user_id > 0
                    ? $user_id === $panel_head_user_id
                    : (int) ($reviewer['is_panel_head'] ?? 0) === 1;
                $this->upsert_panel_reviewer(
                    $review_id,
                    $panel_id,
                    $user_id,
                    (float) ($reviewer['weight'] ?? 1),
                    $is_head
                );
            }
        }
    }

    public function copy_from_review(int $source_review_id, int $target_review_id): void
    {
        $this->clear_review_assignments($target_review_id);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->student_panels_table} WHERE review_id = %d",
            $source_review_id
        );
        foreach ($this->wpdb->get_results($sql, 'ARRAY_A') ?: [] as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($student_id <= 0 || $panel_id <= 0) {
                continue;
            }
            $title = trim((string) ($row['project_title'] ?? ''));
            $this->set_student_panel(
                $target_review_id,
                $student_id,
                $panel_id,
                $title !== '' ? $title : null
            );
            $attendance = (string) ($row['attendance_status'] ?? self::ATTENDANCE_PRESENT);
            if ($attendance !== self::ATTENDANCE_PRESENT) {
                $this->set_attendance_status($target_review_id, $student_id, $attendance);
            }
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->panel_reviewers_table} WHERE review_id = %d",
            $source_review_id
        );
        foreach ($this->wpdb->get_results($sql, 'ARRAY_A') ?: [] as $row) {
            $this->upsert_panel_reviewer(
                $target_review_id,
                (int) ($row['panel_id'] ?? 0),
                (int) ($row['user_id'] ?? 0),
                (float) ($row['weight'] ?? 1),
                (int) ($row['is_panel_head'] ?? 0) === 1
            );
        }
    }

    public function reset_to_session_defaults(int $review_id, int $session_id): void
    {
        $this->seed_from_session_defaults($review_id, $session_id);
    }

    /**
     * Copy session panel reviewers into per-review assignments without touching student panels.
     */
    /**
     * Ensure per-review student panels and panel reviewers match current session defaults.
     */
    public function ensure_assignments_from_session(int $review_id, int $session_id): void
    {
        if ($this->list_student_panels($review_id) === []) {
            $this->seed_from_session_defaults($review_id, $session_id);

            return;
        }

        $this->sync_panel_reviewers_from_session($review_id, $session_id);
    }

    /**
     * Copy session panel reviewers (linked user_ids) into every review round for the project.
     */
    public function sync_panel_reviewers_to_all_reviews(int $session_id): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id <= 0) {
                continue;
            }
            $this->sync_panel_reviewers_from_session($review_id, $session_id);
        }
    }

    public function sync_panel_reviewers_from_session(int $review_id, int $session_id): void
    {
        $panels = new PanelRepository($this->wpdb);
        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id <= 0) {
                continue;
            }
            $panel_head = $panels->find_panel_head($panel_id);
            $panel_head_user_id = $panel_head !== null ? (int) ($panel_head['user_id'] ?? 0) : 0;
            foreach ($panels->list_reviewers($panel_id) as $reviewer) {
                $user_id = (int) ($reviewer['user_id'] ?? 0);
                if ($user_id <= 0) {
                    continue;
                }
                $is_head = $panel_head_user_id > 0
                    ? $user_id === $panel_head_user_id
                    : (int) ($reviewer['is_panel_head'] ?? 0) === 1;
                $this->upsert_panel_reviewer(
                    $review_id,
                    $panel_id,
                    $user_id,
                    (float) ($reviewer['weight'] ?? 1),
                    $is_head
                );
            }
        }
    }

    public function sync_student_to_all_reviews(
        int $session_id,
        int $student_id,
        ?int $panel_id,
        ?string $project_title = null
    ): void {
        if ($project_title === null) {
            $enrolment = (new SessionRepository($this->wpdb))->find_enrolment($session_id, $student_id);
            $title = trim((string) ($enrolment['project_title'] ?? ''));
            $project_title = $title !== '' ? $title : null;
        } else {
            $trimmed = trim($project_title);
            $project_title = $trimmed === '' ? null : $trimmed;
        }

        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id <= 0) {
                continue;
            }
            if ($panel_id !== null && $panel_id > 0) {
                $this->set_student_panel($review_id, $student_id, $panel_id, $project_title);
            } elseif ($project_title !== null) {
                $this->set_student_project_title($review_id, $student_id, $project_title);
            }
        }
    }

    public function sync_project_title_to_all_reviews(
        int $session_id,
        int $student_id,
        ?string $project_title
    ): void {
        $trimmed = $project_title !== null ? trim($project_title) : null;
        $value = ($trimmed === null || $trimmed === '') ? null : $trimmed;

        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id <= 0) {
                continue;
            }
            $existing = $this->get_student_panel($review_id, $student_id);
            if ($existing === null) {
                continue;
            }
            $this->wpdb->update(
                $this->student_panels_table,
                ['project_title' => $value],
                ['review_id' => $review_id, 'student_id' => $student_id],
                ['%s'],
                ['%d', '%d']
            );
        }
    }

    public function remove_student_from_all_reviews(int $session_id, int $student_id): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id > 0) {
                $this->wpdb->delete(
                    $this->student_panels_table,
                    ['review_id' => $review_id, 'student_id' => $student_id],
                    ['%d', '%d']
                );
                $this->wpdb->delete(
                    $this->attendance_by_reviewer_table,
                    ['review_id' => $review_id, 'student_id' => $student_id],
                    ['%d', '%d']
                );
            }
        }
    }

    public function clear_review_assignments(int $review_id): void
    {
        $this->wpdb->delete($this->student_panels_table, ['review_id' => $review_id], ['%d']);
        $this->wpdb->delete($this->panel_reviewers_table, ['review_id' => $review_id], ['%d']);
        $this->wpdb->delete($this->attendance_by_reviewer_table, ['review_id' => $review_id], ['%d']);
    }

    public function set_student_panel(
        int $review_id,
        int $student_id,
        int $panel_id,
        ?string $project_title = null
    ): void {
        $title = $project_title !== null ? trim($project_title) : null;
        if ($title === '') {
            $title = null;
        }

        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->student_panels_table} WHERE review_id = %d AND student_id = %d",
                $review_id,
                $student_id
            ),
            'ARRAY_A'
        );

        if (is_array($existing)) {
            $update = ['panel_id' => $panel_id];
            $format = ['%d'];
            if ($project_title !== null) {
                $update['project_title'] = $title;
                $format[] = '%s';
            }
            $this->wpdb->update(
                $this->student_panels_table,
                $update,
                ['review_id' => $review_id, 'student_id' => $student_id],
                $format,
                ['%d', '%d']
            );

            return;
        }

        $this->wpdb->insert(
            $this->student_panels_table,
            [
                'review_id' => $review_id,
                'student_id' => $student_id,
                'panel_id' => $panel_id,
                'attendance_status' => self::ATTENDANCE_PRESENT,
                'project_title' => $title,
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );
    }

    public function set_student_project_title(int $review_id, int $student_id, ?string $project_title): void
    {
        $title = $project_title !== null ? trim($project_title) : null;
        if ($title === '') {
            $title = null;
        }

        $this->wpdb->update(
            $this->student_panels_table,
            ['project_title' => $title],
            ['review_id' => $review_id, 'student_id' => $student_id],
            ['%s'],
            ['%d', '%d']
        );
    }

    public function resolve_project_title(int $session_id, int $review_id, int $student_id): string
    {
        $assignment = $this->get_student_panel($review_id, $student_id);
        if ($assignment !== null) {
            $title = trim((string) ($assignment['project_title'] ?? ''));
            if ($title !== '') {
                return $title;
            }
        }

        $enrolment = (new SessionRepository($this->wpdb))->find_enrolment($session_id, $student_id);
        if ($enrolment !== null) {
            $title = trim((string) ($enrolment['project_title'] ?? ''));
            if ($title !== '') {
                return $title;
            }
        }

        $meta = (new StudentRepository($this->wpdb))->get_meta($student_id);

        return trim((string) ($meta['project_title'] ?? ''));
    }

    public function get_attendance_status(int $review_id, int $student_id): string
    {
        $row = $this->get_student_panel($review_id, $student_id);
        if ($row === null) {
            return self::ATTENDANCE_PRESENT;
        }

        $status = (string) ($row['attendance_status'] ?? self::ATTENDANCE_PRESENT);

        return in_array($status, [self::ATTENDANCE_PRESENT, self::ATTENDANCE_ABSENT], true)
            ? $status
            : self::ATTENDANCE_PRESENT;
    }

    public function set_attendance_status(int $review_id, int $student_id, string $status): void
    {
        if (!in_array($status, [self::ATTENDANCE_PRESENT, self::ATTENDANCE_ABSENT], true)) {
            throw new \InvalidArgumentException('Invalid attendance status.');
        }

        $this->wpdb->update(
            $this->student_panels_table,
            ['attendance_status' => $status],
            ['review_id' => $review_id, 'student_id' => $student_id],
            ['%s'],
            ['%d', '%d']
        );
    }

    public function upsert_reviewer_attendance_assertion(
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        string $attendance_status
    ): void {
        if (!in_array($attendance_status, [self::ATTENDANCE_PRESENT, self::ATTENDANCE_ABSENT], true)) {
            throw new \InvalidArgumentException('Invalid attendance status.');
        }

        $updated_at = function_exists('current_time')
            ? current_time('mysql')
            : gmdate('Y-m-d H:i:s');

        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->attendance_by_reviewer_table}
                 WHERE review_id = %d AND student_id = %d AND reviewer_user_id = %d",
                $review_id,
                $student_id,
                $reviewer_user_id
            ),
            'ARRAY_A'
        );

        if (is_array($existing)) {
            $this->wpdb->update(
                $this->attendance_by_reviewer_table,
                [
                    'attendance_status' => $attendance_status,
                    'updated_at' => $updated_at,
                ],
                [
                    'review_id' => $review_id,
                    'student_id' => $student_id,
                    'reviewer_user_id' => $reviewer_user_id,
                ],
                ['%s', '%s'],
                ['%d', '%d', '%d']
            );

            return;
        }

        $this->wpdb->insert(
            $this->attendance_by_reviewer_table,
            [
                'review_id' => $review_id,
                'student_id' => $student_id,
                'reviewer_user_id' => $reviewer_user_id,
                'attendance_status' => $attendance_status,
                'updated_at' => $updated_at,
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );
    }

    public function sync_panel_attendance_assertions(
        int $review_id,
        int $student_id,
        int $panel_id,
        string $attendance_status
    ): int {
        $updated = 0;
        foreach ($this->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $this->upsert_reviewer_attendance_assertion(
                $review_id,
                $student_id,
                $user_id,
                $attendance_status
            );
            ++$updated;
        }

        return $updated;
    }

    /**
     * @return list<array{reviewer_user_id: int, attendance_status: string}>
     */
    public function list_attendance_assertions_for_panel_student(
        int $review_id,
        int $student_id,
        int $panel_id
    ): array {
        $panel_reviewer_ids = [];
        foreach ($this->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id > 0) {
                $panel_reviewer_ids[] = $user_id;
            }
        }

        if ($panel_reviewer_ids === []) {
            return [];
        }

        $allowed = array_fill_keys($panel_reviewer_ids, true);
        $sql = $this->wpdb->prepare(
            "SELECT reviewer_user_id, attendance_status
             FROM {$this->attendance_by_reviewer_table}
             WHERE review_id = %d AND student_id = %d",
            $review_id,
            $student_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($rows)) {
            return [];
        }

        $assertions = [];
        foreach ($rows as $row) {
            $reviewer_user_id = (int) ($row['reviewer_user_id'] ?? 0);
            if ($reviewer_user_id <= 0 || !isset($allowed[$reviewer_user_id])) {
                continue;
            }
            $status = (string) ($row['attendance_status'] ?? self::ATTENDANCE_PRESENT);
            if (!in_array($status, [self::ATTENDANCE_PRESENT, self::ATTENDANCE_ABSENT], true)) {
                $status = self::ATTENDANCE_PRESENT;
            }
            $assertions[] = [
                'reviewer_user_id' => $reviewer_user_id,
                'attendance_status' => $status,
            ];
        }

        return $assertions;
    }

    /**
     * @param list<array{student_id: int, panel_id: int, project_title?: string|null}> $assignments
     */
    public function bulk_set_student_panels(int $review_id, array $assignments): void
    {
        foreach ($assignments as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($student_id <= 0 || $panel_id <= 0) {
                continue;
            }
            $project_title = array_key_exists('project_title', $row)
                ? (string) ($row['project_title'] ?? '')
                : null;
            $this->set_student_panel($review_id, $student_id, $panel_id, $project_title);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_student_panel(int $review_id, int $student_id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->student_panels_table} WHERE review_id = %d AND student_id = %d",
            $review_id,
            $student_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_student_panels(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->student_panels_table} WHERE review_id = %d ORDER BY student_id ASC",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function upsert_panel_reviewer(
        int $review_id,
        int $panel_id,
        int $user_id,
        float $weight = 1.0,
        ?bool $is_panel_head = null
    ): int {
        $weight = $weight > 0 ? $weight : 1.0;
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->panel_reviewers_table}
             WHERE review_id = %d AND panel_id = %d AND user_id = %d",
            $review_id,
            $panel_id,
            $user_id
        );
        $existing = $this->wpdb->get_row($sql, 'ARRAY_A');

        if (is_array($existing)) {
            $id = (int) ($existing['id'] ?? 0);
            $update = ['weight' => $weight];
            $format = ['%f'];
            if ($is_panel_head !== null) {
                $update['is_panel_head'] = $is_panel_head ? 1 : 0;
                $format[] = '%d';
            }
            $this->wpdb->update(
                $this->panel_reviewers_table,
                $update,
                ['id' => $id],
                $format,
                ['%d']
            );

            return $id;
        }

        $this->wpdb->insert(
            $this->panel_reviewers_table,
            [
                'review_id' => $review_id,
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'weight' => $weight,
                'is_panel_head' => ($is_panel_head ?? false) ? 1 : 0,
            ],
            ['%d', '%d', '%d', '%f', '%d']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function delete_panel_reviewer(int $review_id, int $panel_id, int $user_id): bool
    {
        return $this->wpdb->delete(
            $this->panel_reviewers_table,
            [
                'review_id' => $review_id,
                'panel_id' => $panel_id,
                'user_id' => $user_id,
            ],
            ['%d', '%d', '%d']
        ) !== false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_panel_reviewers(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->panel_reviewers_table} WHERE review_id = %d ORDER BY panel_id ASC, user_id ASC",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function is_reviewer_on_panel(int $review_id, int $panel_id, int $user_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->panel_reviewers_table}
             WHERE review_id = %d AND panel_id = %d AND user_id = %d",
            $review_id,
            $panel_id,
            $user_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row);
    }

    public function is_panel_head_for_user(int $review_id, int $panel_id, int $user_id): bool
    {
        foreach ($this->list_panel_reviewers($review_id) as $row) {
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
     * @return list<array<string, mixed>>
     */
    public function list_panel_reviewers_for_panel(int $review_id, int $panel_id): array
    {
        $rows = array_values(array_filter(
            $this->list_panel_reviewers($review_id),
            static fn (array $row): bool => (int) ($row['panel_id'] ?? 0) === $panel_id
        ));

        usort(
            $rows,
            static fn (array $a, array $b): int => (int) ($a['user_id'] ?? 0) <=> (int) ($b['user_id'] ?? 0)
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function panels_for_user(int $review_id, int $session_id, int $user_id): array
    {
        $panels = new PanelRepository($this->wpdb);
        $matched = [];
        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id > 0 && $this->is_reviewer_on_panel($review_id, $panel_id, $user_id)) {
                $matched[] = $panel;
            }
        }

        return $matched;
    }

    public function count_unassigned_students(int $review_id, int $session_id): int
    {
        $sessions = new SessionRepository($this->wpdb);
        $count = 0;
        foreach ($sessions->list_enrolled($session_id) as $enrolment) {
            $student_id = (int) ($enrolment['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }
            $assignment = $this->get_student_panel($review_id, $student_id);
            if ($assignment === null || (int) ($assignment['panel_id'] ?? 0) <= 0) {
                ++$count;
            }
        }

        return $count;
    }

    public function count_unassigned_all_reviews(int $session_id): int
    {
        $reviews = new ReviewRepository($this->wpdb);
        $total = 0;
        foreach ($reviews->list_for_session($session_id) as $review) {
            $total += $this->count_unassigned_students((int) ($review['id'] ?? 0), $session_id);
        }

        return $total;
    }

    public function get_reviewer_weight(int $review_id, int $panel_id, int $user_id): float
    {
        $sql = $this->wpdb->prepare(
            "SELECT weight FROM {$this->panel_reviewers_table}
             WHERE review_id = %d AND panel_id = %d AND user_id = %d",
            $review_id,
            $panel_id,
            $user_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');
        if (!is_array($row)) {
            return 1.0;
        }

        $weight = (float) ($row['weight'] ?? 1);

        return $weight > 0 ? $weight : 1.0;
    }

    public function find_previous_review_id(int $session_id, int $review_id): ?int
    {
        $reviews = new ReviewRepository($this->wpdb);
        $list = $reviews->list_for_session($session_id);
        $previous = null;
        foreach ($list as $review) {
            $id = (int) ($review['id'] ?? 0);
            if ($id === $review_id) {
                return $previous;
            }
            $previous = $id;
        }

        return null;
    }
}
