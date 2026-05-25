<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class SessionRepository
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    private object $wpdb;

    private string $sessions_table;

    private string $enrolment_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('SessionRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->sessions_table = $this->wpdb->prefix . 'pr_sessions';
        $this->enrolment_table = $this->wpdb->prefix . 'pr_session_students';
    }

    /**
     * @param array{title?: string, status?: string} $data
     */
    public function create(array $data = []): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $status = (string) ($data['status'] ?? self::STATUS_DRAFT);
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $status = self::STATUS_DRAFT;
        }

        $this->wpdb->insert(
            $this->sessions_table,
            [
                'title' => trim((string) ($data['title'] ?? '')),
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->sessions_table} WHERE id = %d",
            $id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_all(?string $status = null): array
    {
        if ($status !== null && $status !== '') {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->sessions_table} WHERE status = %s ORDER BY updated_at DESC, id DESC",
                $status
            );
        } else {
            $sql = "SELECT * FROM {$this->sessions_table} ORDER BY updated_at DESC, id DESC";
        }

        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array{title?: string, status?: string} $data
     */
    public function update(int $id, array $data): bool
    {
        $row = [];
        $format = [];

        if (array_key_exists('title', $data)) {
            $row['title'] = trim((string) $data['title']);
            $format[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            if (!in_array($status, self::VALID_STATUSES, true)) {
                return false;
            }
            $row['status'] = $status;
            $format[] = '%s';
        }

        if ($row === []) {
            return true;
        }

        $row['updated_at'] = gmdate('Y-m-d H:i:s');
        $format[] = '%s';

        $this->wpdb->update(
            $this->sessions_table,
            $row,
            ['id' => $id],
            $format,
            ['%d']
        );

        return true;
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete(
            $this->enrolment_table,
            ['session_id' => $id],
            ['%d']
        );

        return $this->wpdb->delete(
            $this->sessions_table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    public function enrol_student(
        int $session_id,
        int $student_id,
        ?int $panel_id = null,
        ?string $project_title = null,
        ?string $guide_emp_id = null,
        ?string $guide_name = null
    ): int {
        $existing = $this->find_enrolment($session_id, $student_id);
        if ($existing !== null) {
            if ($panel_id !== null) {
                $this->assign_panel($session_id, $student_id, $panel_id);
            }
            if ($project_title !== null) {
                $this->update_project_title($session_id, $student_id, $project_title);
            }
            if ($guide_emp_id !== null || $guide_name !== null) {
                $this->update_guide(
                    $session_id,
                    $student_id,
                    $guide_emp_id,
                    $guide_name
                );
            }

            return (int) $existing['id'];
        }

        $title = self::normalize_project_title($project_title);

        $this->wpdb->insert(
            $this->enrolment_table,
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
                'panel_id' => $panel_id,
                'project_title' => $title,
                'guide_emp_id' => trim((string) ($guide_emp_id ?? '')),
                'guide_name' => trim((string) ($guide_name ?? '')),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );

        $enrolment_id = (int) $this->wpdb->insert_id;
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        if ($panel_id !== null && $panel_id > 0) {
            $assignments->sync_student_to_all_reviews($session_id, $student_id, $panel_id, $title);
        } elseif ($title !== null) {
            $assignments->sync_project_title_to_all_reviews($session_id, $student_id, $title);
        }

        return $enrolment_id;
    }

    public function update_project_title(int $session_id, int $student_id, ?string $project_title): void
    {
        $title = self::normalize_project_title($project_title);

        $this->wpdb->update(
            $this->enrolment_table,
            ['project_title' => $title],
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
            ],
            ['%s'],
            ['%d', '%d']
        );

        (new ReviewAssignmentRepository($this->wpdb))->sync_project_title_to_all_reviews(
            $session_id,
            $student_id,
            $title
        );
    }

    private static function normalize_project_title(?string $project_title): ?string
    {
        if ($project_title === null) {
            return null;
        }

        $trimmed = trim($project_title);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_enrolment(int $session_id, int $student_id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->enrolment_table} WHERE session_id = %d AND student_id = %d",
            $session_id,
            $student_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_enrolled(int $session_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->enrolment_table} WHERE session_id = %d ORDER BY id ASC",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function remove_enrolment(int $session_id, int $student_id): bool
    {
        (new ReviewAssignmentRepository($this->wpdb))->remove_student_from_all_reviews($session_id, $student_id);

        return $this->wpdb->delete(
            $this->enrolment_table,
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
            ],
            ['%d', '%d']
        ) !== false;
    }

    public function assign_panel(int $session_id, int $student_id, ?int $panel_id): bool
    {
        $this->wpdb->update(
            $this->enrolment_table,
            ['panel_id' => $panel_id],
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
            ],
            ['%d'],
            ['%d', '%d']
        );

        if ($panel_id !== null && $panel_id > 0) {
            (new ReviewAssignmentRepository($this->wpdb))->sync_student_to_all_reviews(
                $session_id,
                $student_id,
                $panel_id
            );
        }

        return true;
    }

    public function update_guide(
        int $session_id,
        int $student_id,
        ?string $guide_emp_id = null,
        ?string $guide_name = null
    ): void {
        $row = [];
        $format = [];

        if ($guide_emp_id !== null) {
            $row['guide_emp_id'] = trim($guide_emp_id);
            $format[] = '%s';
        }
        if ($guide_name !== null) {
            $row['guide_name'] = trim($guide_name);
            $format[] = '%s';
        }

        if ($row === []) {
            return;
        }

        $this->wpdb->update(
            $this->enrolment_table,
            $row,
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
            ],
            $format,
            ['%d', '%d']
        );
    }

    public function count_enrolled(int $session_id): int
    {
        return count($this->list_enrolled($session_id));
    }

    /**
     * @param list<int> $session_ids
     * @return array<int, int>
     */
    public function count_enrolled_for_sessions(array $session_ids): array
    {
        $session_ids = array_values(
            array_unique(
                array_filter(
                    array_map(static fn ($id): int => (int) $id, $session_ids),
                    static fn (int $id): bool => $id > 0
                )
            )
        );

        if ($session_ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
        $sql = $this->wpdb->prepare(
            "SELECT session_id, COUNT(*) AS enrolled_count
             FROM {$this->enrolment_table}
             WHERE session_id IN ({$placeholders})
             GROUP BY session_id",
            ...$session_ids
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        $counts = array_fill_keys($session_ids, 0);
        if (!is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $counts[(int) $row['session_id']] = (int) $row['enrolled_count'];
        }

        return $counts;
    }

    /**
     * @param list<int|numeric-string> $student_ids
     * @return array{
     *     enrolled: list<int>,
     *     skipped: list<array{student_id: int, reason: string}>
     * }
     */
    public function enrol_students_bulk(int $session_id, array $student_ids, StudentRepository $students): array
    {
        $result = [
            'enrolled' => [],
            'skipped' => [],
        ];
        $seen = [];

        foreach ($student_ids as $raw_id) {
            $student_id = (int) $raw_id;
            if ($student_id <= 0) {
                continue;
            }

            if (isset($seen[$student_id])) {
                $result['skipped'][] = [
                    'student_id' => $student_id,
                    'reason' => 'duplicate',
                ];
                continue;
            }
            $seen[$student_id] = true;

            if ($students->find_by_id($student_id) === null) {
                $result['skipped'][] = [
                    'student_id' => $student_id,
                    'reason' => 'not_found',
                ];
                continue;
            }

            if ($this->find_enrolment($session_id, $student_id) !== null) {
                $result['skipped'][] = [
                    'student_id' => $student_id,
                    'reason' => 'already_enrolled',
                ];
                continue;
            }

            $this->enrol_student($session_id, $student_id);
            $result['enrolled'][] = $student_id;
        }

        return $result;
    }

    public function count_unassigned(int $session_id): int
    {
        $count = 0;
        foreach ($this->list_enrolled($session_id) as $row) {
            if (empty($row['panel_id'])) {
                $count++;
            }
        }

        return $count;
    }

    public function count_students_for_panel(int $session_id, int $panel_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrolment_table}
             WHERE session_id = %d AND panel_id = %d",
            $session_id,
            $panel_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * @param list<array{
     *   reg_no: string,
     *   name?: string,
     *   program?: string,
     *   batch?: string,
     *   panel?: string,
     *   project_title?: string,
     *   guide_emp_id?: string,
     *   guide_name?: string
     * }> $rows
     * @return array{
     *     enrolled: int,
     *     updated: int,
     *     failed: int,
     *     errors: list<array{row: int, reg_no: string, message: string}>
     * }
     */
    public function import_enrolment(int $session_id, array $rows, StudentRepository $students): array
    {
        $panels = new PanelRepository($this->wpdb);
        $result = [
            'enrolled' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $reg_no = trim((string) ($row['reg_no'] ?? ''));
            if ($reg_no === '') {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'reg_no' => '',
                    'message' => 'Registration number is required.',
                ];
                continue;
            }

            $student = $students->resolve_for_enrolment($row);
            if ($student instanceof \WP_Error) {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'reg_no' => $reg_no,
                    'message' => $student->get_error_message(),
                ];
                continue;
            }

            $panel_id = null;
            $panel_name = trim((string) ($row['panel'] ?? $row['panel_name'] ?? ''));
            if ($panel_name !== '') {
                $panel = $panels->find_by_name($session_id, $panel_name);
                if ($panel === null) {
                    $panel_id = $panels->create($session_id, $panel_name);
                } else {
                    $panel_id = (int) $panel['id'];
                }
            }

            $project_title = array_key_exists('project_title', $row)
                ? (string) ($row['project_title'] ?? '')
                : null;
            $guide_emp_id = array_key_exists('guide_emp_id', $row)
                ? (string) ($row['guide_emp_id'] ?? '')
                : null;
            $guide_name = array_key_exists('guide_name', $row)
                ? (string) ($row['guide_name'] ?? '')
                : null;

            $existing = $this->find_enrolment($session_id, (int) $student['id']);
            if ($existing === null) {
                $this->enrol_student(
                    $session_id,
                    (int) $student['id'],
                    $panel_id,
                    $project_title,
                    $guide_emp_id,
                    $guide_name
                );
                $result['enrolled']++;
            } else {
                if ($panel_id !== null) {
                    $this->assign_panel($session_id, (int) $student['id'], $panel_id);
                }
                if ($project_title !== null) {
                    $this->update_project_title($session_id, (int) $student['id'], $project_title);
                }
                if ($guide_emp_id !== null || $guide_name !== null) {
                    $this->update_guide(
                        $session_id,
                        (int) $student['id'],
                        $guide_emp_id,
                        $guide_name
                    );
                }
                $result['updated']++;
            }
        }

        return $result;
    }
}
