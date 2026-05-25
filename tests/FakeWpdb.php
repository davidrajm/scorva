<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

/**
 * In-memory $wpdb stub for repository unit tests.
 */
final class FakeWpdb
{
    public string $prefix = 'wp_';

    public int $insert_id = 0;

    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    /** @var array<string, list<string>> */
    private array $table_column_defs = [];

    /** @var array<string, true> */
    private array $views = [];

    /** @var list<string> */
    public array $queries = [];

    public function get_charset_collate(): string
    {
        return 'utf8mb4_unicode_ci';
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function _real_escape(string $string): string
    {
        return addslashes($string);
    }

    /**
     * @param list<string> $columns
     */
    public function register_table_columns(string $table, array $columns): void
    {
        $this->table_column_defs[$table] = $columns;
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
    }

    public function has_column(string $table, string $column): bool
    {
        return in_array($column, $this->table_column_defs[$table] ?? [], true);
    }

    public function has_view(string $view): bool
    {
        return isset($this->views[$view]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_all_rows(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    public function query(string $sql): int
    {
        $this->queries[] = $sql;
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? '';

        if (preg_match('/^DROP VIEW IF EXISTS (\S+)$/i', $normalized, $matches) === 1) {
            unset($this->views[$matches[1]]);

            return 1;
        }

        if (preg_match('/^DROP VIEW (\S+)$/i', $normalized, $matches) === 1) {
            unset($this->views[$matches[1]]);

            return 1;
        }

        if (preg_match('/^DROP TABLE IF EXISTS (\S+)$/i', $normalized, $matches) === 1) {
            $table = $matches[1];
            unset($this->tables[$table], $this->table_column_defs[$table]);

            return 1;
        }

        if (preg_match('/^TRUNCATE TABLE (\S+)$/i', $normalized, $matches) === 1) {
            $table = $matches[1];
            $this->tables[$table] = [];

            return 1;
        }

        if (str_starts_with($normalized, 'CREATE VIEW ')) {
            if (preg_match('/^CREATE VIEW (\S+) AS/', $normalized, $matches) === 1) {
                $this->views[$matches[1]] = true;

                return 1;
            }
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN reason text/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'reason')) {
                $columns = $this->table_column_defs[$table];
                $reviewer_index = array_search('reviewer_user_id', $columns, true);
                if ($reviewer_index !== false) {
                    array_splice($columns, $reviewer_index + 1, 0, ['reason']);
                } else {
                    $columns[] = 'reason';
                }
                $this->table_column_defs[$table] = $columns;
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    if (!array_key_exists('reason', $row)) {
                        $this->tables[$table][$index]['reason'] = '';
                    }
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN attendance_status varchar\(16\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'attendance_status')) {
                $columns = $this->table_column_defs[$table];
                $panel_index = array_search('panel_id', $columns, true);
                if ($panel_index !== false) {
                    array_splice($columns, $panel_index + 1, 0, ['attendance_status']);
                } else {
                    $columns[] = 'attendance_status';
                }
                $this->table_column_defs[$table] = $columns;
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    if (!array_key_exists('attendance_status', $row)) {
                        $this->tables[$table][$index]['attendance_status'] = 'present';
                    }
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN project_title varchar\(500\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'project_title')) {
                $columns = $this->table_column_defs[$table];
                if (str_contains($table, 'pr_review_student_panels')) {
                    $after = array_search('attendance_status', $columns, true);
                    if ($after !== false) {
                        array_splice($columns, $after + 1, 0, ['project_title']);
                    } else {
                        $columns[] = 'project_title';
                    }
                } else {
                    $after = array_search('panel_id', $columns, true);
                    if ($after !== false) {
                        array_splice($columns, $after + 1, 0, ['project_title']);
                    } else {
                        $columns[] = 'project_title';
                    }
                }
                $this->table_column_defs[$table] = $columns;
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN is_panel_head tinyint\(1\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'is_panel_head')) {
                $columns = $this->table_column_defs[$table];
                $weight_index = array_search('weight', $columns, true);
                if ($weight_index !== false) {
                    array_splice($columns, $weight_index + 1, 0, ['is_panel_head']);
                } else {
                    $columns[] = 'is_panel_head';
                }
                $this->table_column_defs[$table] = $columns;
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    if (!array_key_exists('is_panel_head', $row)) {
                        $this->tables[$table][$index]['is_panel_head'] = 0;
                    }
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN coordinator_marks_locked tinyint\(1\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'coordinator_marks_locked')) {
                $columns = $this->table_column_defs[$table];
                $active_index = array_search('marking_active', $columns, true);
                if ($active_index !== false) {
                    array_splice($columns, $active_index + 1, 0, ['coordinator_marks_locked']);
                } else {
                    $columns[] = 'coordinator_marks_locked';
                }
                $this->table_column_defs[$table] = $columns;
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    if (!array_key_exists('coordinator_marks_locked', $row)) {
                        $this->tables[$table][$index]['coordinator_marks_locked'] = 0;
                    }
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN program varchar\(64\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'program')) {
                $columns = $this->table_column_defs[$table];
                $name_index = array_search('name', $columns, true);
                if ($name_index !== false) {
                    array_splice($columns, $name_index + 1, 0, ['program']);
                } else {
                    $columns[] = 'program';
                }
                $this->table_column_defs[$table] = $columns;
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    if (!array_key_exists('program', $row)) {
                        $this->tables[$table][$index]['program'] = '';
                    }
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN guide_emp_id varchar\(64\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'guide_emp_id')) {
                $columns = $this->table_column_defs[$table];
                $after = array_search('project_title', $columns, true);
                if ($after !== false) {
                    array_splice($columns, $after + 1, 0, ['guide_emp_id']);
                } else {
                    $columns[] = 'guide_emp_id';
                }
                $this->table_column_defs[$table] = $columns;
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN guide_name varchar\(255\)/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if (!isset($this->table_column_defs[$table])) {
                $this->table_column_defs[$table] = [];
            }
            if (!$this->has_column($table, 'guide_name')) {
                $columns = $this->table_column_defs[$table];
                $after = array_search('guide_emp_id', $columns, true);
                if ($after !== false) {
                    array_splice($columns, $after + 1, 0, ['guide_name']);
                } else {
                    $columns[] = 'guide_name';
                }
                $this->table_column_defs[$table] = $columns;
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) DROP COLUMN guide_name/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            if ($this->has_column($table, 'guide_name')) {
                $this->table_column_defs[$table] = array_values(
                    array_filter(
                        $this->table_column_defs[$table] ?? [],
                        static fn (string $col): bool => $col !== 'guide_name'
                    )
                );
                foreach ($this->tables[$table] ?? [] as $index => $row) {
                    unset($this->tables[$table][$index]['guide_name']);
                }
            }

            return 1;
        }

        if (preg_match(
            '/UPDATE (\S+) ss INNER JOIN (\S+) s ON s\.id = ss\.student_id SET ss\.guide_name = s\.guide_name/',
            $normalized,
            $matches
        ) === 1) {
            $enrol_table = $matches[1];
            $student_table = $matches[2];
            foreach ($this->tables[$enrol_table] ?? [] as $index => $enrol) {
                $student_id = (int) ($enrol['student_id'] ?? 0);
                $current_guide = trim((string) ($enrol['guide_name'] ?? ''));
                if ($current_guide !== '') {
                    continue;
                }
                foreach ($this->tables[$student_table] ?? [] as $student) {
                    if ((int) ($student['id'] ?? 0) !== $student_id) {
                        continue;
                    }
                    $registry_guide = trim((string) ($student['guide_name'] ?? ''));
                    if ($registry_guide !== '') {
                        $this->tables[$enrol_table][$index]['guide_name'] = $registry_guide;
                    }
                    break;
                }
            }

            return 1;
        }

        if (preg_match(
            '/ALTER TABLE (\S+) ADD COLUMN name varchar\(255\)/',
            $normalized,
            $matches
        ) !== 1) {
            return 0;
        }

        $table = $matches[1];
        if (!isset($this->table_column_defs[$table])) {
            $this->table_column_defs[$table] = [];
        }

        if ($this->has_column($table, 'name')) {
            return 1;
        }

        $columns = $this->table_column_defs[$table];
        $panel_index = array_search('panel_id', $columns, true);
        if ($panel_index !== false) {
            array_splice($columns, $panel_index + 1, 0, ['name']);
        } else {
            $columns[] = 'name';
        }
        $this->table_column_defs[$table] = $columns;

        foreach ($this->tables[$table] ?? [] as $index => $row) {
            if (!array_key_exists('name', $row)) {
                $this->tables[$table][$index]['name'] = '';
            }
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>|null $format
     */
    public function insert(string $table, array $data, ?array $format = null): int
    {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        if (!isset($data['id'])) {
            $this->insert_id = $this->next_id($table);
            $data['id'] = $this->insert_id;
        } else {
            $this->insert_id = (int) $data['id'];
        }

        $this->tables[$table][] = $data;

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @param list<string>|null $format
     * @param list<string>|null $where_format
     */
    public function update(
        string $table,
        array $data,
        array $where,
        ?array $format = null,
        ?array $where_format = null
    ): int {
        if (!isset($this->tables[$table])) {
            return 0;
        }

        foreach ($this->tables[$table] as $index => $row) {
            if (!$this->row_matches($row, $where)) {
                continue;
            }

            foreach ($data as $key => $value) {
                $this->tables[$table][$index][$key] = $value;
            }

            return 1;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $where
     * @param list<string>|null $where_format
     */
    public function delete(string $table, array $where, ?array $where_format = null): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }

        $before = count($this->tables[$table]);
        $this->tables[$table] = array_values(
            array_filter(
                $this->tables[$table],
                fn (array $row): bool => !$this->row_matches($row, $where)
            )
        );

        return $before - count($this->tables[$table]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_row(string $query, string $output = 'OBJECT'): ?array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? '';

        if (preg_match(
            '/SHOW CREATE TABLE `([^`]+)`/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            $columns = $this->table_column_defs[$table] ?? array_keys($this->tables[$table][0] ?? []);
            if ($columns === []) {
                return null;
            }

            $defs = array_map(
                static fn (string $col): string => '`' . $col . '` longtext',
                $columns
            );

            return [
                'Table' => $table,
                'Create Table' => 'CREATE TABLE `' . $table . '` (' . implode(', ', $defs) . ')',
            ];
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_id($matches[1], (int) $matches[2]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE reg_no = \'((?:[^\']|\'\')*)\'/',
            $normalized,
            $matches
        ) === 1) {
            $reg_no = str_replace("''", "'", $matches[2]);

            return $this->find_by_column($matches[1], 'reg_no', $reg_no);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE field_key = \'((?:[^\']|\'\')*)\'/',
            $normalized,
            $matches
        ) === 1) {
            $field_key = str_replace("''", "'", $matches[2]);

            return $this->find_by_column($matches[1], 'field_key', $field_key);
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE reg_no = \'((?:[^\']|\'\')*)\'(?: AND id != (\d+))? LIMIT 1/',
            $normalized,
            $matches
        ) === 1) {
            $reg_no = str_replace("''", "'", $matches[2]);
            $exclude = isset($matches[3]) ? (int) $matches[3] : null;
            $row = $this->find_by_column($matches[1], 'reg_no', $reg_no);
            if ($row === null) {
                return null;
            }
            if ($exclude !== null && (int) ($row['id'] ?? 0) === $exclude) {
                return null;
            }

            return ['id' => $row['id']];
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE student_id = (\d+) AND field_key = \'((?:[^\']|\'\')*)\'/',
            $normalized,
            $matches
        ) === 1) {
            $field_key = str_replace("''", "'", $matches[3]);
            $row = $this->find_meta_row((int) $matches[2], $field_key);

            return $row !== null ? ['id' => $row['id']] : null;
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'student_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND name = \'((?:[^\']|\'\')*)\'/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'name' => str_replace("''", "'", $matches[3]),
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+) AND student_id = (\d+) AND reviewer_user_id = (\d+) AND criterion_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'review_id' => (int) $matches[3],
                'student_id' => (int) $matches[4],
                'reviewer_user_id' => (int) $matches[5],
                'criterion_id' => (int) $matches[6],
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d)/',
            $normalized,
            $matches
        ) === 1 && str_contains($normalized, 'review_weights')) {
            return $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'review_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE review_id = (\d+) AND reviewer_user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'reviewer_user_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT id, provisioned_for_session FROM (\S+) WHERE session_id = (\d+) AND user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'user_id' => (int) $matches[3],
            ]);

            return $row !== null
                ? [
                    'id' => $row['id'],
                    'provisioned_for_session' => $row['provisioned_for_session'] ?? 0,
                ]
                : null;
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND status = \'((?:[^\']|\'\')*)\'/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_first_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'status' => str_replace("''", "'", $matches[3]),
            ], true);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'review_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE review_id = (\d+) AND reviewer_user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'reviewer_user_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE review_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'student_id' => (int) $matches[3],
            ]);
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE review_id = (\d+) AND student_id = (\d+) AND reviewer_user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'student_id' => (int) $matches[3],
                'reviewer_user_id' => (int) $matches[4],
            ]);

            return $row !== null ? ['id' => $row['id']] : null;
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE review_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'student_id' => (int) $matches[3],
            ]);

            return $row !== null ? ['id' => $row['id']] : null;
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE review_id = (\d+) AND panel_id = (\d+) AND user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'panel_id' => (int) $matches[3],
                'user_id' => (int) $matches[4],
            ]);

            return $row !== null ? ['id' => $row['id']] : null;
        }

        if (preg_match(
            '/SELECT weight FROM (\S+) WHERE review_id = (\d+) AND panel_id = (\d+) AND user_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'review_id' => (int) $matches[2],
                'panel_id' => (int) $matches[3],
                'user_id' => (int) $matches[4],
            ]);

            return $row !== null ? ['weight' => $row['weight'] ?? 1] : null;
        }

        if (preg_match(
            '/SELECT title FROM (\S+) WHERE id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);

            return $row !== null ? ['title' => $row['title'] ?? ''] : null;
        }

        if (preg_match(
            '/SELECT label FROM (\S+) WHERE id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);

            return $row !== null ? ['label' => $row['label'] ?? ''] : null;
        }

        if (preg_match(
            '/SELECT name FROM (\S+) WHERE id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);

            return $row !== null ? ['name' => $row['name'] ?? ''] : null;
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+) AND panel_id = (\d+) AND reviewer_user_id = (\d+) AND status = \'((?:[^\']|\'\')*)\' ORDER BY id DESC LIMIT 1/',
            $normalized,
            $matches
        ) === 1) {
            $rows = $this->filter_and_sort(
                $matches[1],
                [
                    'session_id' => (int) $matches[2],
                    'review_id' => (int) $matches[3],
                    'panel_id' => (int) $matches[4],
                    'reviewer_user_id' => (int) $matches[5],
                    'status' => str_replace("''", "'", $matches[6]),
                ],
                'ORDER BY id DESC'
            );

            return $rows[0] ?? null;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_results(string $query, string $output = 'OBJECT'): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? '';

        if (preg_match(
            '/SELECT \* FROM `([^`]+)`/',
            $normalized,
            $matches
        ) === 1 && !str_contains($normalized, ' WHERE ')) {
            return $this->tables[$matches[1]] ?? [];
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE session_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $rows = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]) {
                    $rows[] = ['id' => $row['id']];
                }
            }

            return $rows;
        }

        if (preg_match(
            '/SELECT session_id FROM (\S+) WHERE id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);

            return $row !== null ? [['session_id' => $row['session_id'] ?? 0]] : [];
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                ['session_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE status = \'((?:[^\']|\'\')*)\' ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                ['status' => str_replace("''", "'", $matches[2])],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE panel_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                ['panel_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE review_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                ['review_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE review_id = (\d+)/',
            $normalized,
            $matches
        ) === 1 && !str_contains($normalized, 'ORDER BY')
        ) {
            return $this->filter_and_sort(
                $matches[1],
                ['review_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+)/',
            $normalized,
            $matches
        ) === 1 && !str_contains($normalized, 'ORDER BY')
        ) {
            return $this->filter_and_sort(
                $matches[1],
                ['session_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE panel_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                ['panel_id' => (int) $matches[2]],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $row = $this->find_by_columns($matches[1], [
                'session_id' => (int) $matches[2],
                'student_id' => (int) $matches[3],
            ]);

            return $row !== null ? [$row] : [];
        }

        if (preg_match('/SELECT \* FROM (\S+) ORDER BY/', $normalized, $matches) === 1) {
            return $this->filter_and_sort($matches[1], [], $normalized);
        }

        if (preg_match(
            "/SHOW COLUMNS FROM (\S+) LIKE '((?:[^']|'')*)'/",
            $normalized,
            $matches
        ) === 1) {
            $column = str_replace("''", "'", $matches[2]);
            if ($this->has_column($matches[1], $column)) {
                return [['Field' => $column, 'Type' => 'varchar(255)']];
            }

            return [];
        }

        if (preg_match(
            "/SHOW FULL TABLES LIKE '((?:[^']|'')*)'/",
            $normalized,
            $matches
        ) === 1) {
            $table = str_replace("''", "'", $matches[1]);
            if (isset($this->views[$table])) {
                return [['Tables_in_test' => $table, 'Table_type' => 'VIEW']];
            }

            return [];
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE reg_no LIKE/',
            $normalized,
            $matches
        ) === 1) {
            if (preg_match_all("/'((?:[^']|'')*)'/", $normalized, $like_matches) < 1) {
                return [];
            }
            $needle = str_replace("''", "'", $like_matches[1][0] ?? '');
            $needle = trim($needle, '%');
            $table = $matches[1];
            $rows = [];
            foreach ($this->tables[$table] ?? [] as $row) {
                foreach (['reg_no', 'name', 'program', 'batch'] as $col) {
                    if (isset($row[$col]) && stripos((string) $row[$col], $needle) !== false) {
                        $rows[] = $row;
                        break;
                    }
                }
            }
            usort(
                $rows,
                static fn (array $a, array $b): int => strcmp((string) ($a['reg_no'] ?? ''), (string) ($b['reg_no'] ?? ''))
            );

            return $rows;
        }

        if (preg_match(
            '/SELECT reviewer_user_id, attendance_status FROM (\S+) WHERE review_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $review_id = (int) $matches[2];
            $student_id = (int) $matches[3];
            $rows = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['review_id'] ?? 0) !== $review_id) {
                    continue;
                }
                if ((int) ($row['student_id'] ?? 0) !== $student_id) {
                    continue;
                }
                $reviewer_user_id = (int) ($row['reviewer_user_id'] ?? 0);
                if ($reviewer_user_id <= 0) {
                    continue;
                }
                $rows[] = [
                    'reviewer_user_id' => $reviewer_user_id,
                    'attendance_status' => (string) ($row['attendance_status'] ?? 'present'),
                ];
            }

            return $rows;
        }

        if (preg_match(
            '/SELECT field_key, meta_value FROM (\S+) WHERE student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $student_id = (int) $matches[2];
            $rows = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['student_id'] ?? 0) === $student_id) {
                    $rows[] = [
                        'field_key' => $row['field_key'],
                        'meta_value' => $row['meta_value'],
                    ];
                }
            }

            return $rows;
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+) AND student_id = (\d+) AND reviewer_user_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                [
                    'session_id' => (int) $matches[2],
                    'review_id' => (int) $matches[3],
                    'student_id' => (int) $matches[4],
                    'reviewer_user_id' => (int) $matches[5],
                ],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+) AND reviewer_user_id = (\d+) ORDER BY student_id ASC, criterion_id ASC/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                [
                    'session_id' => (int) $matches[2],
                    'review_id' => (int) $matches[3],
                    'reviewer_user_id' => (int) $matches[4],
                ],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT \* FROM (\S+) WHERE session_id = (\d+) AND review_id = (\d+) AND student_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->filter_and_sort(
                $matches[1],
                [
                    'session_id' => (int) $matches[2],
                    'review_id' => (int) $matches[3],
                    'student_id' => (int) $matches[4],
                ],
                $normalized
            );
        }

        if (preg_match(
            '/SELECT m\.session_id, m\.review_id, s\.reg_no, m\.reviewer_user_id, m\.criterion_id, m\.score, m\.status, m\.flagged FROM (\S+) m INNER JOIN (\S+) s ON s\.id = m\.student_id WHERE m\.session_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            return $this->marks_joined_to_students(
                $matches[1],
                $matches[2],
                (int) $matches[3]
            );
        }

        if (preg_match(
            '/SELECT session_id, COUNT\(\*\) AS enrolled_count FROM (\S+) WHERE session_id IN \(([^)]+)\) GROUP BY session_id/',
            $normalized,
            $matches
        ) === 1) {
            $table = $matches[1];
            $session_ids = array_map('intval', array_map('trim', explode(',', $matches[2])));
            $counts = [];
            foreach ($this->tables[$table] ?? [] as $row) {
                $session_id = (int) ($row['session_id'] ?? 0);
                if (!in_array($session_id, $session_ids, true)) {
                    continue;
                }
                $counts[$session_id] = ($counts[$session_id] ?? 0) + 1;
            }

            $rows = [];
            foreach ($counts as $session_id => $count) {
                $rows[] = [
                    'session_id' => $session_id,
                    'enrolled_count' => $count,
                ];
            }

            return $rows;
        }

        if (preg_match(
            '/SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score, status, flagged(?:, coordinator_overridden, overridden_from_score)? FROM (\S+) WHERE project_id = (\d+) ORDER BY/',
            $normalized,
            $matches
        ) === 1) {
            $marks_table = $this->prefix . 'pr_marks';
            $students_table = $this->prefix . 'pr_students';
            $joined = $this->marks_joined_to_students(
                $marks_table,
                $students_table,
                (int) $matches[2]
            );

            return array_map(
                static fn (array $row): array => [
                    'project_id' => (int) ($row['session_id'] ?? 0),
                    'review_id' => (int) ($row['review_id'] ?? 0),
                    'reg_no' => (string) ($row['reg_no'] ?? ''),
                    'reviewer_id' => (int) ($row['reviewer_user_id'] ?? 0),
                    'rubric_id' => (int) ($row['criterion_id'] ?? 0),
                    'score' => $row['score'] ?? null,
                    'status' => (string) ($row['status'] ?? ''),
                    'flagged' => $row['flagged'] ?? 0,
                    'coordinator_overridden' => $row['coordinator_overridden'] ?? 0,
                    'overridden_from_score' => $row['overridden_from_score'] ?? null,
                ],
                $joined
            );
        }

        return [];
    }

    /**
     * @return string|int|null
     */
    public function get_var(string $query)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? '';

        if (preg_match(
            "/SHOW TABLES LIKE '((?:[^']|'')*)'/",
            $normalized,
            $matches
        ) === 1) {
            $table = str_replace("''", "'", $matches[1]);
            if (isset($this->views[$table])) {
                return $table;
            }
            if (isset($this->table_column_defs[$table]) || isset($this->tables[$table])) {
                return $table;
            }

            return null;
        }

        if (preg_match(
            "/SHOW FULL TABLES LIKE '((?:[^']|'')*)'/",
            $normalized,
            $matches
        ) === 1) {
            $table = str_replace("''", "'", $matches[1]);
            if (isset($this->views[$table])) {
                return $table;
            }

            return null;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE session_id = (\d+) AND panel_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]
                    && (int) ($row['panel_id'] ?? 0) === (int) $matches[3]) {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE session_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]) {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE user_id = (\d+) AND disabled_at IS NOT NULL/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['user_id'] ?? 0) === (int) $matches[2]
                    && !empty($row['disabled_at'])) {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE session_id = (\d+) AND status != \'submitted\'/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]
                    && (string) ($row['status'] ?? '') !== 'submitted') {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+) AND review_id = (\d+) AND status = \'submitted\'/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]
                    && (int) ($row['student_id'] ?? 0) === (int) $matches[3]
                    && (int) ($row['review_id'] ?? 0) === (int) $matches[4]
                    && (string) ($row['status'] ?? '') === 'submitted') {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match('/SELECT status FROM (\S+) WHERE id = (\d+)/', $normalized, $matches) === 1) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);

            return $row['status'] ?? null;
        }

        if (preg_match(
            '/SELECT 1 FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+) AND score IS NOT NULL LIMIT 1/',
            $normalized,
            $matches
        ) === 1) {
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]
                    && (int) ($row['student_id'] ?? 0) === (int) $matches[3]
                    && array_key_exists('score', $row)
                    && $row['score'] !== null) {
                    return '1';
                }
            }

            return null;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE review_id = (\d+) AND score IS NOT NULL/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['review_id'] ?? 0) !== (int) $matches[2]) {
                    continue;
                }
                if (!array_key_exists('score', $row) || $row['score'] === null || $row['score'] === '') {
                    continue;
                }
                ++$count;
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['student_id'] ?? 0) === (int) $matches[2]) {
                    ++$count;
                }
            }

            return $count;
        }

        if (preg_match(
            '/SELECT COUNT\(\*\) FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $count = 0;
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]
                    && (int) ($row['student_id'] ?? 0) === (int) $matches[3]) {
                    ++$count;
                }
            }

            return $count;
        }

        return null;
    }

    /**
     * @return list<int|string>
     */
    public function get_col(string $query): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? '';

        if (preg_match(
            '/SELECT DISTINCT reviewer_user_id FROM (\S+) WHERE session_id = (\d+) AND student_id = (\d+) AND review_id = (\d+) AND status = \'([^\']+)\'/',
            $normalized,
            $matches
        ) === 1) {
            $ids = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) !== (int) $matches[2]
                    || (int) ($row['student_id'] ?? 0) !== (int) $matches[3]
                    || (int) ($row['review_id'] ?? 0) !== (int) $matches[4]
                    || (string) ($row['status'] ?? '') !== $matches[5]) {
                    continue;
                }
                $ids[(int) ($row['reviewer_user_id'] ?? 0)] = true;
            }

            return array_keys($ids);
        }

        if (preg_match(
            '/SELECT DISTINCT reviewer_user_id FROM (\S+) WHERE session_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $ids = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]) {
                    $ids[] = $row['reviewer_user_id'] ?? 0;
                }
            }

            return array_values(array_unique($ids));
        }

        if (preg_match(
            '/SELECT id FROM (\S+) WHERE session_id = (\d+)/',
            $normalized,
            $matches
        ) === 1) {
            $ids = [];
            foreach ($this->tables[$matches[1]] ?? [] as $row) {
                if ((int) ($row['session_id'] ?? 0) === (int) $matches[2]) {
                    $ids[] = $row['id'] ?? 0;
                }
            }

            return $ids;
        }

        return [];
    }

    public function prepare(string $query, ...$args): string
    {
        if ($args === []) {
            return $query;
        }

        $escaped = array_map(
            static function ($arg): string {
                if (is_int($arg)) {
                    return (string) $arg;
                }

                return "'" . str_replace("'", "''", (string) $arg) . "'";
            },
            $args
        );

        return vsprintf(str_replace(['%d', '%s'], ['%s', '%s'], $query), $escaped);
    }

    private function next_id(string $table): int
    {
        $max = 0;
        foreach ($this->tables[$table] ?? [] as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marks_joined_to_students(string $marks_table, string $students_table, int $session_id): array
    {
        $rows = [];
        foreach ($this->tables[$marks_table] ?? [] as $mark) {
            if ((int) ($mark['session_id'] ?? 0) !== $session_id) {
                continue;
            }
            $student = $this->find_by_id($students_table, (int) ($mark['student_id'] ?? 0));
            if ($student === null) {
                continue;
            }
            $rows[] = [
                'session_id' => (int) ($mark['session_id'] ?? 0),
                'review_id' => (int) ($mark['review_id'] ?? 0),
                'reg_no' => (string) ($student['reg_no'] ?? ''),
                'reviewer_user_id' => (int) ($mark['reviewer_user_id'] ?? 0),
                'criterion_id' => (int) ($mark['criterion_id'] ?? 0),
                'score' => $mark['score'] ?? null,
                'status' => (string) ($mark['status'] ?? ''),
                'flagged' => $mark['flagged'] ?? 0,
            ];
        }
        usort(
            $rows,
            static fn (array $a, array $b): int => [
                $a['review_id'],
                $a['reg_no'],
                $a['reviewer_user_id'],
                $a['criterion_id'],
            ] <=> [
                $b['review_id'],
                $b['reg_no'],
                $b['reviewer_user_id'],
                $b['criterion_id'],
            ]
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_by_id(string $table, int $id): ?array
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_by_column(string $table, string $column, string $value): ?array
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if ((string) ($row[$column] ?? '') === $value) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, int|string|null> $where
     * @return array<string, mixed>|null
     */
    private function find_by_columns(string $table, array $where): ?array
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($this->row_matches($row, $where)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, int|string|null> $where
     * @return array<string, mixed>|null
     */
    private function find_first_by_columns(string $table, array $where, bool $list): ?array
    {
        unset($list);

        return $this->find_by_columns($table, $where);
    }

    /**
     * @param array<string, int|string|null> $where
     * @return list<array<string, mixed>>
     */
    private function filter_and_sort(string $table, array $where, string $normalized): array
    {
        $rows = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($where === [] || $this->row_matches($row, $where)) {
                $rows[] = $row;
            }
        }

        usort(
            $rows,
            static function (array $a, array $b) use ($normalized): int {
                if (str_contains($normalized, 'updated_at DESC')) {
                    return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''))
                        ?: ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
                }
                if (str_contains($normalized, 'sort_order')) {
                    return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
                        ?: ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                if (str_contains($normalized, 'name ASC')) {
                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
                        ?: ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                if (str_contains($normalized, 'criterion_id ASC')) {
                    return ((int) ($a['criterion_id'] ?? 0)) <=> ((int) ($b['criterion_id'] ?? 0));
                }
                if (str_contains($normalized, 'reviewer_user_id ASC')) {
                    return ((int) ($a['reviewer_user_id'] ?? 0)) <=> ((int) ($b['reviewer_user_id'] ?? 0))
                        ?: ((int) ($a['criterion_id'] ?? 0)) <=> ((int) ($b['criterion_id'] ?? 0));
                }

                return strcmp((string) ($a['reg_no'] ?? ''), (string) ($b['reg_no'] ?? ''));
            }
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where
     */
    private function row_matches(array $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            if ((string) ($row[$key] ?? '') !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_meta_row(int $student_id, string $field_key): ?array
    {
        $table = $this->prefix . 'pr_student_meta';
        foreach ($this->tables[$table] ?? [] as $row) {
            if ((int) ($row['student_id'] ?? 0) === $student_id
                && (string) ($row['field_key'] ?? '') === $field_key) {
                return $row;
            }
        }

        return null;
    }
}
