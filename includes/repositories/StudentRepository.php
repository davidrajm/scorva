<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class StudentRepository
{
    private object $wpdb;

    private string $table;

    private string $meta_table;

    private string $enrolment_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('StudentRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'pr_students';
        $this->meta_table = $this->wpdb->prefix . 'pr_student_meta';
        $this->enrolment_table = $this->wpdb->prefix . 'pr_session_students';
    }

    /**
     * @param array{
     *     reg_no: string,
     *     name?: string,
     *     program?: string,
     *     batch?: string,
     *     meta?: array<string, string>
     * } $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'reg_no' => $data['reg_no'],
            'name' => $data['name'] ?? '',
            'program' => $data['program'] ?? '',
            'batch' => $data['batch'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->wpdb->insert(
            $this->table,
            $row,
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        $id = (int) $this->wpdb->insert_id;
        if (isset($data['meta']) && is_array($data['meta'])) {
            $this->set_meta($id, $data['meta']);
        }

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        if (!is_array($row)) {
            return null;
        }

        $row['meta'] = $this->get_meta($id);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_reg_no(string $reg_no): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE reg_no = %s",
            $reg_no
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        if (!is_array($row)) {
            return null;
        }

        $row['meta'] = $this->get_meta((int) $row['id']);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_all(?string $search = null): array
    {
        if ($search !== null && $search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE reg_no LIKE %s OR name LIKE %s OR program LIKE %s OR batch LIKE %s
                ORDER BY reg_no ASC",
                $like,
                $like,
                $like,
                $like
            );
        } else {
            $sql = "SELECT * FROM {$this->table} ORDER BY reg_no ASC";
        }

        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['meta'] = $this->get_meta((int) $row['id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array{
     *     reg_no?: string,
     *     name?: string,
     *     program?: string,
     *     batch?: string,
     *     meta?: array<string, string>
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['reg_no', 'name', 'program', 'batch'];
        $row = [];
        $format = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $row[$key] = $data[$key];
            $format[] = '%s';
        }

        if ($row !== []) {
            $row['updated_at'] = gmdate('Y-m-d H:i:s');
            $format[] = '%s';
            $this->wpdb->update(
                $this->table,
                $row,
                ['id' => $id],
                $format,
                ['%d']
            );
        }

        if (isset($data['meta']) && is_array($data['meta'])) {
            $this->set_meta($id, $data['meta']);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete(
            $this->meta_table,
            ['student_id' => $id],
            ['%d']
        );

        return $this->wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    public function count_enrolments(int $student_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrolment_table} WHERE student_id = %d",
            $student_id
        );
        $count = $this->wpdb->get_var($sql);

        return (int) $count;
    }

    /**
     * Find or create a registry student for project enrolment (CSV / wizard).
     *
     * New students require a non-empty name. Existing students get identity fields
     * updated only when the row provides non-empty name, program, or batch.
     *
     * @param array{
     *     reg_no?: string,
     *     name?: string,
     *     program?: string,
     *     batch?: string
     * } $row
     * @return array<string, mixed>|\WP_Error
     */
    public function resolve_for_enrolment(array $row): array|\WP_Error
    {
        $reg_no = trim((string) ($row['reg_no'] ?? ''));
        if ($reg_no === '') {
            return new \WP_Error(
                'pr_invalid_student',
                __('Registration number is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $name = trim((string) ($row['name'] ?? ''));
        $program = trim((string) ($row['program'] ?? ''));
        $batch = trim((string) ($row['batch'] ?? ''));

        $existing = $this->find_by_reg_no($reg_no);
        if ($existing === null) {
            if ($name === '') {
                return new \WP_Error(
                    'pr_name_required',
                    __('Name is required for new students.', 'scorva'),
                    ['status' => 400]
                );
            }

            $id = $this->insert([
                'reg_no' => $reg_no,
                'name' => $name,
                'program' => $program,
                'batch' => $batch,
            ]);
            $student = $this->find_by_id($id);
            if ($student === null) {
                return new \WP_Error(
                    'pr_student_create_failed',
                    __('Could not create student.', 'scorva'),
                    ['status' => 500]
                );
            }

            return $student;
        }

        $updates = [];
        if ($name !== '') {
            $updates['name'] = $name;
        }
        if ($program !== '') {
            $updates['program'] = $program;
        }
        if ($batch !== '') {
            $updates['batch'] = $batch;
        }

        if ($updates !== []) {
            $this->update((int) $existing['id'], $updates);
            $refreshed = $this->find_by_id((int) $existing['id']);
            if ($refreshed !== null) {
                return $refreshed;
            }
        }

        return $existing;
    }

    public function reg_no_exists(string $reg_no, ?int $exclude_id = null): bool
    {
        if ($exclude_id !== null) {
            $sql = $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE reg_no = %s AND id != %d LIMIT 1",
                $reg_no,
                $exclude_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE reg_no = %s LIMIT 1",
                $reg_no
            );
        }

        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, reg_no: string, message: string}>
     * }
     */
    public function import_rows(array $rows, string $duplicate_policy = 'skip'): array
    {
        $result = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $reg_no = trim((string) ($row['reg_no'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($reg_no === '') {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'reg_no' => '',
                    'message' => 'Registration number is required.',
                ];
                continue;
            }

            if ($name === '') {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'reg_no' => $reg_no,
                    'message' => 'Name is required.',
                ];
                continue;
            }

            $existing = $this->find_by_reg_no($reg_no);
            $payload = [
                'reg_no' => $reg_no,
                'name' => $name,
                'program' => trim((string) ($row['program'] ?? '')),
                'batch' => trim((string) ($row['batch'] ?? '')),
            ];

            if (isset($row['meta']) && is_array($row['meta'])) {
                $payload['meta'] = $row['meta'];
            }

            if ($existing !== null) {
                if ($duplicate_policy === 'update') {
                    $this->update((int) $existing['id'], $payload);
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            try {
                $this->insert($payload);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'reg_no' => $reg_no,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function get_meta(int $student_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT field_key, meta_value FROM {$this->meta_table} WHERE student_id = %d",
            $student_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($rows)) {
            return [];
        }

        $meta = [];
        foreach ($rows as $row) {
            $meta[(string) $row['field_key']] = (string) $row['meta_value'];
        }

        return $meta;
    }

    /**
     * @param array<string, string> $meta
     */
    public function set_meta(int $student_id, array $meta): void
    {
        foreach ($meta as $field_key => $value) {
            $field_key = (string) $field_key;
            if ($field_key === '') {
                continue;
            }

            $existing = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->meta_table} WHERE student_id = %d AND field_key = %s",
                    $student_id,
                    $field_key
                ),
                'ARRAY_A'
            );

            if (is_array($existing)) {
                $this->wpdb->update(
                    $this->meta_table,
                    ['meta_value' => (string) $value],
                    ['id' => (int) $existing['id']],
                    ['%s'],
                    ['%d']
                );
                continue;
            }

            $this->wpdb->insert(
                $this->meta_table,
                [
                    'student_id' => $student_id,
                    'field_key' => $field_key,
                    'meta_value' => (string) $value,
                ],
                ['%d', '%s', '%s']
            );
        }
    }
}
