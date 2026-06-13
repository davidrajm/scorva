<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class ProgramRepository
{
    private object $wpdb;

    private string $table;

    private string $students_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('ProgramRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'pr_programs';
        $this->students_table = $this->wpdb->prefix . 'pr_students';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY name ASC",
            'ARRAY_A'
        );

        return is_array($rows) ? $rows : [];
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

        return is_array($row) ? $row : null;
    }

    /**
     * Case-insensitive lookup by name.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_name(string $name): ?array
    {
        $canonical = $this->canonical_name($name);
        if ($canonical === '') {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE LOWER(name) = LOWER(%s) LIMIT 1",
            $canonical
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return int|\WP_Error Inserted ID on success.
     */
    public function create(string $name, string $code = ''): int|\WP_Error
    {
        $canonical = $this->canonical_name($name);
        if ($canonical === '') {
            return new \WP_Error(
                'pr_invalid_program',
                __('Program name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $existing = $this->find_by_name($canonical);
        if ($existing !== null) {
            return new \WP_Error(
                'pr_duplicate_program',
                __('A program with this name already exists.', 'scorva'),
                ['status' => 409]
            );
        }

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'name' => $canonical,
                'code' => trim($code),
            ],
            ['%s', '%s']
        );

        if ($inserted === false) {
            return new \WP_Error(
                'pr_program_create_failed',
                __('Could not create program.', 'scorva'),
                ['status' => 500]
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Rename a program and update all student rows that referenced the old name.
     *
     * @return bool|\WP_Error
     */
    public function rename(int $id, string $new_name): bool|\WP_Error
    {
        $canonical = $this->canonical_name($new_name);
        if ($canonical === '') {
            return new \WP_Error(
                'pr_invalid_program',
                __('Program name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $existing = $this->find_by_id($id);
        if ($existing === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $conflict = $this->find_by_name($canonical);
        if ($conflict !== null && (int) $conflict['id'] !== $id) {
            return new \WP_Error(
                'pr_duplicate_program',
                __('A program with this name already exists.', 'scorva'),
                ['status' => 409]
            );
        }

        $old_name = (string) ($existing['name'] ?? '');

        $this->wpdb->update(
            $this->table,
            ['name' => $canonical],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        // Rewrite student rows that used the old canonical name.
        if ($old_name !== '' && $old_name !== $canonical) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->students_table} SET program = %s WHERE LOWER(program) = LOWER(%s)",
                    $canonical,
                    $old_name
                )
            );
        }

        return true;
    }

    /**
     * Merge source program into target: rewrite student rows then delete the source entry.
     *
     * @return bool|\WP_Error
     */
    public function merge(int $source_id, int $target_id): bool|\WP_Error
    {
        if ($source_id === $target_id) {
            return new \WP_Error(
                'pr_invalid_merge',
                __('Source and target program must be different.', 'scorva'),
                ['status' => 400]
            );
        }

        $source = $this->find_by_id($source_id);
        if ($source === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Source program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $target = $this->find_by_id($target_id);
        if ($target === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Target program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $source_name = (string) ($source['name'] ?? '');
        $target_name = (string) ($target['name'] ?? '');

        // Rewrite student rows from source to target.
        if ($source_name !== '') {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->students_table} SET program = %s WHERE LOWER(program) = LOWER(%s)",
                    $target_name,
                    $source_name
                )
            );
        }

        // Delete the source entry.
        $this->wpdb->delete(
            $this->table,
            ['id' => $source_id],
            ['%d']
        );

        return true;
    }

    /**
     * Delete a program only if no students are assigned to it.
     */
    public function delete(int $id): bool|\WP_Error
    {
        $program = $this->find_by_id($id);
        if ($program === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $name = (string) ($program['name'] ?? '');
        if ($name !== '') {
            $count = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->students_table} WHERE LOWER(program) = LOWER(%s)",
                    $name
                )
            );

            if ($count > 0) {
                return new \WP_Error(
                    'pr_program_in_use',
                    sprintf(
                        /* translators: %d: number of students */
                        _n(
                            'Cannot delete: %d student is assigned to this program.',
                            'Cannot delete: %d students are assigned to this program.',
                            $count,
                            'scorva'
                        ),
                        $count
                    ),
                    ['status' => 409]
                );
            }
        }

        return $this->wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * Trim and normalize internal whitespace runs to a single space.
     */
    private function canonical_name(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);
    }
}
