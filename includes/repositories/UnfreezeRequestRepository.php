<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class UnfreezeRequestRepository
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_GRANTED = 'granted';

    private object $wpdb;

    private string $table;

    private string $sessions_table;

    private string $reviews_table;

    private string $panels_table;

    private string $panel_reviewers_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('UnfreezeRequestRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $prefix = $this->wpdb->prefix;
        $this->table = $prefix . 'pr_unfreeze_requests';
        $this->sessions_table = $prefix . 'pr_sessions';
        $this->reviews_table = $prefix . 'pr_reviews';
        $this->panels_table = $prefix . 'pr_panels';
        $this->panel_reviewers_table = $prefix . 'pr_review_panel_reviewers';
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
    public function find_pending_for_assignment(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE session_id = %d AND review_id = %d AND panel_id = %d
               AND reviewer_user_id = %d AND status = %s
             ORDER BY id DESC LIMIT 1",
            $session_id,
            $review_id,
            $panel_id,
            $reviewer_user_id,
            self::STATUS_PENDING
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    public function has_pending(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id
    ): bool {
        return $this->find_pending_for_assignment($session_id, $review_id, $panel_id, $reviewer_user_id) !== null;
    }

    /**
     * @return array<string, mixed> Existing pending row or newly created row.
     */
    public function create_pending(
        int $session_id,
        int $review_id,
        int $panel_id,
        int $reviewer_user_id,
        string $reason
    ): array {
        $existing = $this->find_pending_for_assignment(
            $session_id,
            $review_id,
            $panel_id,
            $reviewer_user_id
        );
        if ($existing !== null) {
            return $existing;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->wpdb->insert(
            $this->table,
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'panel_id' => $panel_id,
                'reviewer_user_id' => $reviewer_user_id,
                'reason' => $reason,
                'status' => self::STATUS_PENDING,
                'requested_at' => $now,
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        $id = (int) $this->wpdb->insert_id;
        $row = $this->find_by_id($id);

        return $row ?? [
            'id' => $id,
            'session_id' => $session_id,
            'review_id' => $review_id,
            'panel_id' => $panel_id,
            'reviewer_user_id' => $reviewer_user_id,
            'status' => self::STATUS_PENDING,
            'requested_at' => $now,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_pending_for_coordinator(int $limit = 50): array
    {
        $limit = max(1, min(50, $limit));
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE status = %s
             ORDER BY requested_at ASC
             LIMIT %d",
            self::STATUS_PENDING,
            $limit
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $session_id = (int) ($row['session_id'] ?? 0);
            $review_id = (int) ($row['review_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);

            $session = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT title FROM {$this->sessions_table} WHERE id = %d",
                    $session_id
                ),
                'ARRAY_A'
            );
            $review = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT label FROM {$this->reviews_table} WHERE id = %d",
                    $review_id
                ),
                'ARRAY_A'
            );
            $panel = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT name FROM {$this->panels_table} WHERE id = %d",
                    $panel_id
                ),
                'ARRAY_A'
            );

            $row['session_title'] = is_array($session) ? (string) ($session['title'] ?? '') : '';
            $row['review_label'] = is_array($review) ? (string) ($review['label'] ?? '') : '';
            $row['panel_name'] = is_array($panel) ? (string) ($panel['name'] ?? '') : '';

            $out[] = $this->format_list_row($row);
        }

        return $out;
    }

    /**
     * Pending reviewer-mark requests for panels where the user is panel coordinator.
     *
     * @return list<array<string, mixed>>
     */
    public function list_pending_for_panel_head(int $panel_head_user_id, int $limit = 50): array
    {
        $limit = max(1, min(50, $limit));
        $sql = $this->wpdb->prepare(
            "SELECT ur.* FROM {$this->table} ur
             INNER JOIN {$this->panel_reviewers_table} pr
               ON pr.review_id = ur.review_id
              AND pr.panel_id = ur.panel_id
              AND pr.user_id = %d
              AND pr.is_panel_head = 1
             WHERE ur.status = %s
             ORDER BY ur.requested_at ASC
             LIMIT %d",
            $panel_head_user_id,
            self::STATUS_PENDING,
            $limit
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $session_id = (int) ($row['session_id'] ?? 0);
            $review_id = (int) ($row['review_id'] ?? 0);
            $panel_id = (int) ($row['panel_id'] ?? 0);

            $session = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT title FROM {$this->sessions_table} WHERE id = %d",
                    $session_id
                ),
                'ARRAY_A'
            );
            $review = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT label FROM {$this->reviews_table} WHERE id = %d",
                    $review_id
                ),
                'ARRAY_A'
            );
            $panel = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT name FROM {$this->panels_table} WHERE id = %d",
                    $panel_id
                ),
                'ARRAY_A'
            );

            $row['session_title'] = is_array($session) ? (string) ($session['title'] ?? '') : '';
            $row['review_label'] = is_array($review) ? (string) ($review['label'] ?? '') : '';
            $row['panel_name'] = is_array($panel) ? (string) ($panel['name'] ?? '') : '';

            $out[] = $this->format_list_row($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function grant(int $id, int $resolved_by_user_id): ?array
    {
        $row = $this->find_by_id($id);
        if ($row === null || (string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return null;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_GRANTED,
                'resolved_at' => $now,
                'resolved_by_user_id' => $resolved_by_user_id,
            ],
            ['id' => $id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        return $this->find_by_id($id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function format_list_row(array $row): array
    {
        $reviewer_user_id = (int) ($row['reviewer_user_id'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'session_id' => (int) ($row['session_id'] ?? 0),
            'session_title' => (string) ($row['session_title'] ?? ''),
            'review_id' => (int) ($row['review_id'] ?? 0),
            'review_label' => (string) ($row['review_label'] ?? ''),
            'panel_id' => (int) ($row['panel_id'] ?? 0),
            'panel_name' => (string) ($row['panel_name'] ?? ''),
            'reviewer_user_id' => $reviewer_user_id,
            'reviewer_name' => $this->reviewer_display_name($reviewer_user_id),
            'reason' => (string) ($row['reason'] ?? ''),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
        ];
    }

    private function reviewer_display_name(int $user_id): string
    {
        if ($user_id <= 0 || !function_exists('get_userdata')) {
            return '';
        }

        $user = get_userdata($user_id);
        if ($user !== null && !empty($user->display_name)) {
            return (string) $user->display_name;
        }

        return '';
    }

    public function delete_all_for_review(int $review_id): void
    {
        $this->wpdb->delete($this->table, ['review_id' => $review_id], ['%d']);
    }
}
