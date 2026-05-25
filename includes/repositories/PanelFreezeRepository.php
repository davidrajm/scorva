<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class PanelFreezeRepository
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
            throw new \RuntimeException('PanelFreezeRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . 'pr_review_panel_freezes';
    }

    public function is_frozen(int $review_id, int $panel_id): bool
    {
        return $this->find($review_id, $panel_id) !== null;
    }

    /**
     * @return list<int>
     */
    public function list_frozen_panel_ids(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT panel_id FROM {$this->table} WHERE review_id = %d",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($rows)) {
            return [];
        }

        $panel_ids = [];
        foreach ($rows as $row) {
            $panel_id = (int) ($row['panel_id'] ?? 0);
            if ($panel_id > 0) {
                $panel_ids[] = $panel_id;
            }
        }

        return $panel_ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $review_id, int $panel_id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE review_id = %d",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if ((int) ($row['panel_id'] ?? 0) === $panel_id) {
                return $row;
            }
        }

        return null;
    }

    public function freeze(int $review_id, int $panel_id, int $frozen_by_user_id, ?string $pdf_sha256 = null): int
    {
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $row = [
            'review_id' => $review_id,
            'panel_id' => $panel_id,
            'frozen_by_user_id' => $frozen_by_user_id,
            'frozen_at' => $now,
        ];
        $format = ['%d', '%d', '%d', '%s'];

        if ($pdf_sha256 !== null && $pdf_sha256 !== '') {
            $row['pdf_sha256'] = $pdf_sha256;
            $format[] = '%s';
        }

        $this->wpdb->insert($this->table, $row, $format);

        return (int) $this->wpdb->insert_id;
    }

    public function unfreeze(int $review_id, int $panel_id): bool
    {
        $deleted = $this->wpdb->delete(
            $this->table,
            [
                'review_id' => $review_id,
                'panel_id' => $panel_id,
            ],
            ['%d', '%d']
        );

        return $deleted !== false && $deleted > 0;
    }

    public function delete_all_for_review(int $review_id): void
    {
        $this->wpdb->delete($this->table, ['review_id' => $review_id], ['%d']);
    }
}
