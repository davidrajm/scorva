<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class AuditService
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
            throw new \RuntimeException('AuditService requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'pr_mark_audit';
    }

    public function log(
        string $action,
        string $entity,
        int $entity_id,
        ?string $old_value = null,
        ?string $new_value = null,
        ?int $actor_user_id = null
    ): void {
        if ($actor_user_id === null) {
            $actor_user_id = function_exists('get_current_user_id')
                ? (int) get_current_user_id()
                : 0;
        }

        $this->wpdb->insert(
            $this->table,
            [
                'actor_user_id' => $actor_user_id,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entity_id,
                'old_value' => $old_value,
                'new_value' => $new_value,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list_for_session(int $session_id, int $page = 1, int $per_page = 50): array
    {
        $mark_ids = $this->mark_ids_for_session($session_id);
        $filtered = [];
        foreach ($this->all_rows() as $row) {
            $entity = (string) ($row['entity'] ?? '');
            $entity_id = (int) ($row['entity_id'] ?? 0);
            if ($entity === 'session' && $entity_id === $session_id) {
                $filtered[] = $this->with_actor_name($row);
                continue;
            }
            if ($entity === 'mark' && in_array($entity_id, $mark_ids, true)) {
                $filtered[] = $this->with_actor_name($row);
            }
        }

        usort(
            $filtered,
            static fn (array $a, array $b): int => strcmp(
                (string) ($b['created_at'] ?? ''),
                (string) ($a['created_at'] ?? '')
            )
        );

        return $this->paginate($filtered, $page, $per_page);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list_paginated(int $page = 1, int $per_page = 50): array
    {
        $rows = array_map(
            fn (array $row): array => $this->with_actor_name($row),
            $this->all_rows()
        );
        usort(
            $rows,
            static fn (array $a, array $b): int => strcmp(
                (string) ($b['created_at'] ?? ''),
                (string) ($a['created_at'] ?? '')
            )
        );

        return $this->paginate($rows, $page, $per_page);
    }

    public function delete_scoped_rows_for_session(int $session_id): void
    {
        $mark_ids = $this->mark_ids_for_session($session_id);
        foreach ($mark_ids as $mark_id) {
            $this->wpdb->delete(
                $this->table,
                ['entity' => 'mark', 'entity_id' => $mark_id],
                ['%s', '%d']
            );
        }

        $this->wpdb->delete(
            $this->table,
            ['entity' => 'session', 'entity_id' => $session_id],
            ['%s', '%d']
        );
    }

    /**
     * @return list<int>
     */
    private function mark_ids_for_session(int $session_id): array
    {
        $table = $this->wpdb->prefix . 'pr_marks';
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %d",
            $session_id
        );
        $ids = $this->wpdb->get_col($sql);
        if (!is_array($ids)) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function all_rows(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC, id DESC";
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function with_actor_name(array $row): array
    {
        $actor_id = (int) ($row['actor_user_id'] ?? 0);
        $name = (string) $actor_id;
        if ($actor_id > 0 && function_exists('get_userdata')) {
            $user = get_userdata($actor_id);
            if ($user !== null && isset($user->display_name) && $user->display_name !== '') {
                $name = (string) $user->display_name;
            }
        }
        $row['actor_name'] = $name;

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    private function paginate(array $rows, int $page, int $per_page): array
    {
        $page = max(1, $page);
        $per_page = min(100, max(1, $per_page));
        $total = count($rows);
        $offset = ($page - 1) * $per_page;

        return [
            'items' => array_slice($rows, $offset, $per_page),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
}
