<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class MarkRepository
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    private object $wpdb;

    private string $marks_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('MarkRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->marks_table = $this->wpdb->prefix . 'pr_marks';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(int $id): ?array
    {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->marks_table} WHERE id = %d", $id);
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    public function student_has_numeric_scores_in_session(int $session_id, int $student_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->marks_table}
             WHERE session_id = %d AND student_id = %d AND score IS NOT NULL
             LIMIT 1",
            $session_id,
            $student_id
        );
        $found = $this->wpdb->get_var($sql);

        return $found !== null && $found !== '';
    }

    public function count_open_for_session(int $session_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->marks_table} WHERE session_id = %d AND status != %s",
            $session_id,
            self::STATUS_SUBMITTED
        );
        $count = $this->wpdb->get_var($sql);

        return (int) $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_entry(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        int $criterion_id
    ): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table}
             WHERE session_id = %d AND review_id = %d AND student_id = %d
               AND reviewer_user_id = %d AND criterion_id = %d",
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            $criterion_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_for_student_review(
        int $session_id,
        int $review_id,
        int $student_id,
        ?int $reviewer_user_id = null
    ): array {
        if ($reviewer_user_id !== null) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->marks_table}
                 WHERE session_id = %d AND review_id = %d AND student_id = %d
                   AND reviewer_user_id = %d ORDER BY criterion_id ASC",
                $session_id,
                $review_id,
                $student_id,
                $reviewer_user_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->marks_table}
                 WHERE session_id = %d AND review_id = %d AND student_id = %d
                 ORDER BY reviewer_user_id ASC, criterion_id ASC",
                $session_id,
                $review_id,
                $student_id
            );
        }

        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_for_review(int $review_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table} WHERE review_id = %d ORDER BY id ASC",
            $review_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_for_session(int $session_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table} WHERE session_id = %d ORDER BY id ASC",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<int>
     */
    public function list_reviewer_user_ids_for_student_review(
        int $session_id,
        int $student_id,
        int $review_id
    ): array {
        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT reviewer_user_id FROM {$this->marks_table}
             WHERE session_id = %d AND student_id = %d AND review_id = %d AND status = %s",
            $session_id,
            $student_id,
            $review_id,
            self::STATUS_SUBMITTED
        );
        $rows = $this->wpdb->get_col($sql);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map('intval', $rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_for_reviewer_review(
        int $session_id,
        int $review_id,
        int $reviewer_user_id
    ): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->marks_table}
             WHERE session_id = %d AND review_id = %d AND reviewer_user_id = %d
             ORDER BY student_id ASC, criterion_id ASC",
            $session_id,
            $review_id,
            $reviewer_user_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    public function is_student_frozen_for_reviewer(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        int $criteria_count
    ): bool {
        if ($criteria_count <= 0) {
            return false;
        }

        $rows = $this->list_for_student_review($session_id, $review_id, $student_id, $reviewer_user_id);
        if (count($rows) < $criteria_count) {
            return false;
        }

        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') !== self::STATUS_SUBMITTED) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $student_ids
     */
    public function submit_for_students(
        int $session_id,
        int $review_id,
        int $reviewer_user_id,
        array $student_ids
    ): int {
        if ($student_ids === []) {
            return 0;
        }

        $updated = 0;
        foreach ($this->list_for_reviewer_review($session_id, $review_id, $reviewer_user_id) as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            if (!in_array($student_id, $student_ids, true)) {
                continue;
            }
            if ((string) ($row['status'] ?? '') === self::STATUS_SUBMITTED) {
                continue;
            }
            $this->wpdb->update(
                $this->marks_table,
                ['status' => self::STATUS_SUBMITTED],
                ['id' => (int) ($row['id'] ?? 0)],
                ['%s'],
                ['%d']
            );
            ++$updated;
        }

        return $updated;
    }

    /**
     * @param list<int> $student_ids
     */
    public function revert_to_draft_for_students(
        int $session_id,
        int $review_id,
        int $reviewer_user_id,
        array $student_ids
    ): int {
        if ($student_ids === []) {
            return 0;
        }

        $updated = 0;
        foreach ($this->list_for_reviewer_review($session_id, $review_id, $reviewer_user_id) as $row) {
            $student_id = (int) ($row['student_id'] ?? 0);
            if (!in_array($student_id, $student_ids, true)) {
                continue;
            }
            if ((string) ($row['status'] ?? '') !== self::STATUS_SUBMITTED) {
                continue;
            }
            $this->wpdb->update(
                $this->marks_table,
                ['status' => self::STATUS_DRAFT],
                ['id' => (int) ($row['id'] ?? 0)],
                ['%s'],
                ['%d']
            );
            ++$updated;
        }

        return $updated;
    }

    public function upsert(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id,
        int $criterion_id,
        ?float $score,
        string $status,
        bool $flagged = false
    ): int {
        $existing = $this->find_entry(
            $session_id,
            $review_id,
            $student_id,
            $reviewer_user_id,
            $criterion_id
        );

        if ($existing !== null) {
            $this->wpdb->update(
                $this->marks_table,
                [
                    'score' => $score,
                    'status' => $status,
                    'flagged' => $flagged ? 1 : 0,
                ],
                ['id' => (int) $existing['id']],
                ['%f', '%s', '%d'],
                ['%d']
            );

            return (int) $existing['id'];
        }

        $this->wpdb->insert(
            $this->marks_table,
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => $student_id,
                'reviewer_user_id' => $reviewer_user_id,
                'criterion_id' => $criterion_id,
                'score' => $score,
                'flagged' => $flagged ? 1 : 0,
                'status' => $status,
            ],
            ['%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function apply_coordinator_override(int $mark_id, float $score): void
    {
        $existing = $this->find_by_id($mark_id);
        if ($existing === null) {
            return;
        }

        $data = [
            'score' => $score,
            'status' => self::STATUS_SUBMITTED,
            'coordinator_overridden' => 1,
        ];
        $formats = ['%f', '%s', '%d'];

        if ((int) ($existing['coordinator_overridden'] ?? 0) !== 1) {
            if ($existing['score'] !== null && $existing['score'] !== '') {
                $data['overridden_from_score'] = (float) $existing['score'];
                $formats[] = '%f';
            }
        }

        $this->wpdb->update(
            $this->marks_table,
            $data,
            ['id' => $mark_id],
            $formats,
            ['%d']
        );
    }

    public function count_entered_scores_for_review(int $review_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->marks_table} WHERE review_id = %d AND score IS NOT NULL",
            $review_id
        );
        $count = $this->wpdb->get_var($sql);

        return (int) $count;
    }

    public function count_entered_scores_for_session(int $session_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->marks_table} WHERE session_id = %d AND score IS NOT NULL",
            $session_id
        );
        $count = $this->wpdb->get_var($sql);

        return (int) $count;
    }

    public function delete_all_for_review(int $review_id): int
    {
        $deleted = $this->wpdb->delete($this->marks_table, ['review_id' => $review_id], ['%d']);

        return $deleted !== false ? (int) $deleted : 0;
    }

    public function delete_all_for_student_in_session(int $session_id, int $student_id): int
    {
        $deleted = $this->wpdb->delete(
            $this->marks_table,
            [
                'session_id' => $session_id,
                'student_id' => $student_id,
            ],
            ['%d', '%d']
        );

        return $deleted !== false ? (int) $deleted : 0;
    }

    public function session_has_student_with_numeric_scores(int $session_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->marks_table}
             WHERE session_id = %d AND score IS NOT NULL
             LIMIT 1",
            $session_id
        );
        $found = $this->wpdb->get_var($sql);

        return $found !== null && $found !== '';
    }
}
