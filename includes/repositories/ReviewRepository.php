<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class ReviewRepository
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_UNLOCKED = 'unlocked';

    /** @var list<string> */
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_UNLOCKED,
    ];

    private object $wpdb;

    private string $reviews_table;

    private string $criteria_table;

    private string $review_weights_table;

    private string $reviewer_weights_table;

    private string $marks_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('ReviewRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $prefix = $this->wpdb->prefix;
        $this->reviews_table = $prefix . 'pr_reviews';
        $this->criteria_table = $prefix . 'pr_rubric_criteria';
        $this->review_weights_table = $prefix . 'pr_review_weights';
        $this->reviewer_weights_table = $prefix . 'pr_reviewer_weights';
        $this->marks_table = $prefix . 'pr_marks';
    }

    /**
     * @param array{label?: string, sort_order?: int, status?: string} $data
     */
    public function create(int $session_id, array $data = []): int
    {
        $status = (string) ($data['status'] ?? self::STATUS_DRAFT);
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $status = self::STATUS_DRAFT;
        }

        $marking_active = !empty($data['marking_active']) ? 1 : 0;

        $this->wpdb->insert(
            $this->reviews_table,
            [
                'session_id' => $session_id,
                'label' => trim((string) ($data['label'] ?? '')),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => $status,
                'marking_active' => $marking_active,
            ],
            ['%d', '%s', '%d', '%s', '%d']
        );

        $review_id = (int) $this->wpdb->insert_id;
        if ($review_id <= 0) {
            return 0;
        }

        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $previous_id = $assignments->find_previous_review_id($session_id, $review_id);
        if ($previous_id !== null && $previous_id > 0) {
            $assignments->copy_from_review($previous_id, $review_id);
        } else {
            $assignments->seed_from_session_defaults($review_id, $session_id);
        }

        return $review_id;
    }

    public function set_marking_active(int $review_id, bool $active): bool
    {
        return $this->wpdb->update(
            $this->reviews_table,
            ['marking_active' => $active ? 1 : 0],
            ['id' => $review_id],
            ['%d'],
            ['%d']
        ) > 0;
    }

    public function is_marking_active(int $review_id): bool
    {
        $review = $this->find_by_id($review_id);

        return $review !== null && (int) ($review['marking_active'] ?? 0) === 1;
    }

    public function is_coordinator_marks_locked(int $review_id): bool
    {
        $review = $this->find_by_id($review_id);

        return $review !== null && (int) ($review['coordinator_marks_locked'] ?? 0) === 1;
    }

    public function set_coordinator_marks_locked(int $review_id, bool $locked): bool
    {
        return $this->wpdb->update(
            $this->reviews_table,
            ['coordinator_marks_locked' => $locked ? 1 : 0],
            ['id' => $review_id],
            ['%d'],
            ['%d']
        ) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviews_table} WHERE id = %d",
            $id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_for_session(int $session_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviews_table} WHERE session_id = %d ORDER BY sort_order ASC, id ASC",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function count_for_session(int $session_id): int
    {
        return count($this->list_for_session($session_id));
    }

    public function belongs_to_session(int $review_id, int $session_id): bool
    {
        $review = $this->find_by_id($review_id);

        return $review !== null && (int) ($review['session_id'] ?? 0) === $session_id;
    }

    public function set_status(int $review_id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return false;
        }

        return $this->wpdb->update(
            $this->reviews_table,
            ['status' => $status],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        ) > 0;
    }

    /**
     * @param list<array{id?: int, label?: string, max_marks?: float|int|string, weight?: float|int|string, sort_order?: int}> $criteria
     * @return list<array<string, mixed>>
     */
    public function replace_criteria(int $review_id, array $criteria): array
    {
        $existing = $this->list_criteria($review_id);
        $existing_by_id = [];
        foreach ($existing as $row) {
            $existing_by_id[(int) ($row['id'] ?? 0)] = $row;
        }

        $kept_ids = [];
        $saved = [];
        $sort = 0;
        foreach ($criteria as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $weight = $this->normalize_weight($row['weight'] ?? 1);
            $max_marks = $this->normalize_decimal($row['max_marks'] ?? 0);
            $sort_order = (int) ($row['sort_order'] ?? $sort);
            $criterion_id = (int) ($row['id'] ?? 0);

            if ($criterion_id > 0 && isset($existing_by_id[$criterion_id])) {
                $this->wpdb->update(
                    $this->criteria_table,
                    [
                        'label' => $label,
                        'max_marks' => $max_marks,
                        'weight' => $weight,
                        'sort_order' => $sort_order,
                    ],
                    ['id' => $criterion_id],
                    ['%s', '%f', '%f', '%d'],
                    ['%d']
                );
                $kept_ids[] = $criterion_id;
                $saved[] = [
                    'id' => $criterion_id,
                    'review_id' => $review_id,
                    'label' => $label,
                    'max_marks' => $max_marks,
                    'weight' => $weight,
                    'sort_order' => $sort_order,
                ];
            } else {
                $this->wpdb->insert(
                    $this->criteria_table,
                    [
                        'review_id' => $review_id,
                        'label' => $label,
                        'max_marks' => $max_marks,
                        'weight' => $weight,
                        'sort_order' => $sort_order,
                    ],
                    ['%d', '%s', '%f', '%f', '%d']
                );
                $new_id = (int) $this->wpdb->insert_id;
                $kept_ids[] = $new_id;
                $saved[] = [
                    'id' => $new_id,
                    'review_id' => $review_id,
                    'label' => $label,
                    'max_marks' => $max_marks,
                    'weight' => $weight,
                    'sort_order' => $sort_order,
                ];
            }

            ++$sort;
        }

        foreach ($existing as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !in_array($id, $kept_ids, true)) {
                $this->wpdb->delete($this->criteria_table, ['id' => $id], ['%d']);
            }
        }

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_criteria(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->criteria_table} WHERE review_id = %d ORDER BY sort_order ASC, id ASC",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function count_marks_for_review(int $review_id): int
    {
        return count($this->list_marks_for_review($review_id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_marks_for_review(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table} WHERE review_id = %d",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function flag_marks_for_review(int $review_id): int
    {
        $updated = 0;
        foreach ($this->list_marks_for_review($review_id) as $mark) {
            if ((int) ($mark['flagged'] ?? 0) === 1) {
                continue;
            }

            $this->wpdb->update(
                $this->marks_table,
                ['flagged' => 1],
                ['id' => (int) $mark['id']],
                ['%d'],
                ['%d']
            );
            ++$updated;
        }

        return $updated;
    }

    public function clear_marks_for_review(int $review_id): int
    {
        $marks = $this->list_marks_for_review($review_id);
        foreach ($marks as $mark) {
            $this->wpdb->delete($this->marks_table, ['id' => (int) $mark['id']], ['%d']);
        }

        return count($marks);
    }

    public function count_flagged_marks_for_review(int $review_id): int
    {
        $count = 0;
        foreach ($this->list_marks_for_review($review_id) as $mark) {
            if ((int) ($mark['flagged'] ?? 0) === 1) {
                ++$count;
            }
        }

        return $count;
    }

    public function has_review_weight(int $session_id, int $review_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->review_weights_table} WHERE session_id = %d AND review_id = %d LIMIT 1",
            $session_id,
            $review_id
        );

        return $this->wpdb->get_var($sql) !== null;
    }

    public function get_review_weight(int $session_id, int $review_id): float
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->review_weights_table} WHERE session_id = %d AND review_id = %d",
            $session_id,
            $review_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');
        if (!is_array($row)) {
            return 1.0;
        }

        return $this->normalize_weight($row['weight'] ?? 1);
    }

    public function set_review_weight(int $session_id, int $review_id, float $weight): void
    {
        $weight = $this->normalize_weight($weight);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->review_weights_table} WHERE session_id = %d AND review_id = %d",
            $session_id,
            $review_id
        );
        $existing = $this->wpdb->get_row($sql, 'ARRAY_A');

        if (is_array($existing)) {
            $this->wpdb->update(
                $this->review_weights_table,
                ['weight' => $weight],
                ['id' => (int) $existing['id']],
                ['%f'],
                ['%d']
            );

            return;
        }

        $this->wpdb->insert(
            $this->review_weights_table,
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'weight' => $weight,
            ],
            ['%d', '%d', '%f']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_reviewer_weights(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviewer_weights_table} WHERE review_id = %d",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function get_reviewer_weight(int $review_id, int $reviewer_user_id): float
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviewer_weights_table} WHERE review_id = %d AND reviewer_user_id = %d",
            $review_id,
            $reviewer_user_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');
        if (!is_array($row)) {
            return 1.0;
        }

        return $this->normalize_weight($row['weight'] ?? 1);
    }

    public function set_reviewer_weight(int $review_id, int $reviewer_user_id, float $weight): void
    {
        $weight = $this->normalize_weight($weight);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviewer_weights_table} WHERE review_id = %d AND reviewer_user_id = %d",
            $review_id,
            $reviewer_user_id
        );
        $existing = $this->wpdb->get_row($sql, 'ARRAY_A');

        if (is_array($existing)) {
            $this->wpdb->update(
                $this->reviewer_weights_table,
                ['weight' => $weight],
                ['id' => (int) $existing['id']],
                ['%f'],
                ['%d']
            );

            return;
        }

        $this->wpdb->insert(
            $this->reviewer_weights_table,
            [
                'review_id' => $review_id,
                'reviewer_user_id' => $reviewer_user_id,
                'weight' => $weight,
            ],
            ['%d', '%d', '%f']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_session_weights(int $session_id): array
    {
        $reviews = $this->list_for_session($session_id);
        $payload = [
            'review_weights' => [],
            'reviewer_weights' => [],
        ];

        foreach ($reviews as $review) {
            $review_id = (int) $review['id'];
            $payload['review_weights'][] = [
                'review_id' => $review_id,
                'label' => (string) ($review['label'] ?? ''),
                'weight' => $this->get_review_weight($session_id, $review_id),
            ];

            foreach ($this->list_reviewer_weights($review_id) as $row) {
                $payload['reviewer_weights'][] = [
                    'review_id' => $review_id,
                    'reviewer_user_id' => (int) ($row['reviewer_user_id'] ?? 0),
                    'weight' => $this->normalize_weight($row['weight'] ?? 1),
                ];
            }
        }

        return $payload;
    }

    public function session_has_marks(int $session_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table} WHERE session_id = %d",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) && $rows !== [];
    }

    public function delete(int $review_id): bool
    {
        foreach ($this->list_criteria($review_id) as $row) {
            $this->wpdb->delete($this->criteria_table, ['id' => (int) $row['id']], ['%d']);
        }

        $this->wpdb->delete(
            $this->review_weights_table,
            ['review_id' => $review_id],
            ['%d']
        );
        $this->wpdb->delete(
            $this->reviewer_weights_table,
            ['review_id' => $review_id],
            ['%d']
        );

        (new ReviewAssignmentRepository($this->wpdb))->clear_review_assignments($review_id);

        return $this->wpdb->delete($this->reviews_table, ['id' => $review_id], ['%d']) > 0;
    }

    /**
     * @param float|int|string $value
     */
    private function normalize_weight($value): float
    {
        $weight = (float) $value;

        return $weight > 0 ? $weight : 1.0;
    }

    /**
     * @param float|int|string $value
     */
    private function normalize_decimal($value): float
    {
        return max(0.0, (float) $value);
    }
}
