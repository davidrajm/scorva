<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class FieldDefinitionRepository
{
    private object $wpdb;

    private string $table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('FieldDefinitionRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'pr_field_definitions';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_all(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY sort_order ASC, id ASC";
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

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
     * @return array<string, mixed>|null
     */
    public function find_by_field_key(string $field_key): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE field_key = %s",
            $field_key
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{
     *     field_key: string,
     *     label?: string,
     *     field_type?: string,
     *     sort_order?: int
     * } $data
     */
    public function insert(array $data): int
    {
        $row = [
            'field_key' => $data['field_key'],
            'label' => $data['label'] ?? $data['field_key'],
            'field_type' => $data['field_type'] ?? 'text',
            'sort_order' => $data['sort_order'] ?? 0,
        ];

        $this->wpdb->insert(
            $this->table,
            $row,
            ['%s', '%s', '%s', '%d']
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['field_key', 'label', 'field_type', 'sort_order'];
        $row = [];
        $format = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $row[$key] = $data[$key];
            $format[] = $key === 'sort_order' ? '%d' : '%s';
        }

        if ($row === []) {
            return false;
        }

        return $this->wpdb->update(
            $this->table,
            $row,
            ['id' => $id],
            $format,
            ['%d']
        ) !== false;
    }

    public function delete(int $id): bool
    {
        return $this->wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }
}
