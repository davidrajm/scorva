<?php

declare(strict_types=1);

namespace ProjectReviews\Repositories;

final class PanelRepository
{
    private object $wpdb;

    private string $panels_table;

    private string $reviewers_table;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('PanelRepository requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->panels_table = $this->wpdb->prefix . 'pr_panels';
        $this->reviewers_table = $this->wpdb->prefix . 'pr_panel_reviewers';
    }

    public function create(int $session_id, string $name): int
    {
        $this->wpdb->insert(
            $this->panels_table,
            [
                'session_id' => $session_id,
                'name' => trim($name),
            ],
            ['%d', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->panels_table} WHERE id = %d",
            $id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_name(int $session_id, string $name): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->panels_table} WHERE session_id = %d AND name = %s",
            $session_id,
            trim($name)
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_by_session(int $session_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->panels_table} WHERE session_id = %d ORDER BY name ASC, id ASC",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array{name?: string} $data
     */
    public function update(int $id, array $data): bool
    {
        if (!array_key_exists('name', $data)) {
            return true;
        }

        $this->wpdb->update(
            $this->panels_table,
            ['name' => trim((string) $data['name'])],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return true;
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete(
            $this->reviewers_table,
            ['panel_id' => $id],
            ['%d']
        );

        return $this->wpdb->delete(
            $this->panels_table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * @param array{name?: string, email?: string, weight?: float|int|string, user_id?: int|null} $data
     */
    public function add_reviewer(int $panel_id, array $data): int
    {
        $weight = (float) ($data['weight'] ?? 1);
        if ($weight <= 0) {
            $weight = 1;
        }

        $row = [
            'panel_id' => $panel_id,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'weight' => $weight,
            'is_panel_head' => 0,
        ];
        $format = ['%d', '%s', '%s', '%f', '%d'];

        if (array_key_exists('user_id', $data) && $data['user_id'] !== null) {
            $row['user_id'] = (int) $data['user_id'];
            $format[] = '%d';
        }

        $inserted = $this->wpdb->insert($this->reviewers_table, $row, $format);
        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_reviewer(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviewers_table} WHERE id = %d",
            $id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_reviewers(int $panel_id): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reviewers_table} WHERE panel_id = %d ORDER BY name ASC, id ASC",
            $panel_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    /**
     * Resolve a roster display name for a WordPress user on a panel (user_id and email fallbacks).
     */
    public function display_name_for_user(int $panel_id, int $user_id): string
    {
        if ($panel_id <= 0 || $user_id <= 0) {
            return '';
        }

        $wp_user = function_exists('get_userdata') ? get_userdata($user_id) : false;
        $wp_email = $wp_user ? strtolower(trim((string) ($wp_user->user_email ?? ''))) : '';
        $wp_display = $wp_user ? trim((string) ($wp_user->display_name ?? '')) : '';

        foreach ($this->list_reviewers($panel_id) as $session_reviewer) {
            $roster_user_id = isset($session_reviewer['user_id']) && $session_reviewer['user_id'] !== null && $session_reviewer['user_id'] !== ''
                ? (int) $session_reviewer['user_id']
                : 0;
            $roster_name = trim((string) ($session_reviewer['name'] ?? ''));
            $roster_email = trim((string) ($session_reviewer['email'] ?? ''));

            $matches_user = $roster_user_id === $user_id;
            $matches_email = $wp_email !== ''
                && strtolower($roster_email) === $wp_email;

            if (!$matches_user && !$matches_email) {
                continue;
            }

            if ($roster_name !== '') {
                return $roster_name;
            }

            if ($wp_display !== '') {
                return $wp_display;
            }

            if ($roster_email !== '') {
                return $roster_email;
            }
        }

        if ($wp_display !== '') {
            return $wp_display;
        }

        if ($wp_email !== '') {
            return $wp_email;
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list_reviewers_for_session(int $session_id): array
    {
        $panels = $this->list_by_session($session_id);
        $reviewers = [];
        foreach ($panels as $panel) {
            foreach ($this->list_reviewers((int) $panel['id']) as $reviewer) {
                $reviewer['panel_id'] = (int) $panel['id'];
                $reviewer['panel_name'] = (string) $panel['name'];
                $reviewers[] = $reviewer;
            }
        }

        return $reviewers;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_reviewer_by_email_in_session(int $session_id, string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        foreach ($this->list_by_session($session_id) as $panel) {
            foreach ($this->list_reviewers((int) $panel['id']) as $reviewer) {
                if (strtolower(trim((string) ($reviewer['email'] ?? ''))) === $email) {
                    $reviewer['panel_id'] = (int) $panel['id'];
                    $reviewer['panel_name'] = (string) $panel['name'];

                    return $reviewer;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_reviewer_by_email_on_panel(int $panel_id, string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        foreach ($this->list_reviewers($panel_id) as $reviewer) {
            if (strtolower(trim((string) ($reviewer['email'] ?? ''))) === $email) {
                return $reviewer;
            }
        }

        return null;
    }

    public function delete_reviewers_for_session(int $session_id): int
    {
        $deleted = 0;
        foreach ($this->list_by_session($session_id) as $panel) {
            foreach ($this->list_reviewers((int) $panel['id']) as $reviewer) {
                if ($this->delete_reviewer((int) ($reviewer['id'] ?? 0))) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * @param array{name?: string, email?: string, weight?: float|int|string, user_id?: int|null, panel_id?: int} $data
     */
    public function update_reviewer(int $id, array $data): bool
    {
        $row = [];
        $format = [];

        if (array_key_exists('panel_id', $data)) {
            $panel_id = (int) $data['panel_id'];
            if ($panel_id > 0) {
                $row['panel_id'] = $panel_id;
                $format[] = '%d';
            }
        }

        foreach (['name', 'email'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = (string) $data[$key];
            if ($key === 'email') {
                $value = strtolower(trim($value));
            } else {
                $value = trim($value);
            }
            $row[$key] = $value;
            $format[] = '%s';
        }

        if (array_key_exists('weight', $data)) {
            $weight = (float) $data['weight'];
            if ($weight > 0) {
                $row['weight'] = $weight;
                $format[] = '%f';
            }
        }

        if (array_key_exists('user_id', $data)) {
            $row['user_id'] = $data['user_id'];
            $format[] = '%d';
        }

        if (array_key_exists('is_panel_head', $data)) {
            $row['is_panel_head'] = (int) ((bool) $data['is_panel_head']);
            $format[] = '%d';
        }

        if ($row === []) {
            return true;
        }

        $this->wpdb->update(
            $this->reviewers_table,
            $row,
            ['id' => $id],
            $format,
            ['%d']
        );

        return true;
    }

    public function delete_reviewer(int $id): bool
    {
        return $this->wpdb->delete(
            $this->reviewers_table,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_panel_head(int $panel_id): ?array
    {
        foreach ($this->list_reviewers($panel_id) as $reviewer) {
            if ((int) ($reviewer['is_panel_head'] ?? 0) === 1) {
                return $reviewer;
            }
        }

        return null;
    }

    public function clear_panel_heads(int $panel_id): void
    {
        foreach ($this->list_reviewers($panel_id) as $reviewer) {
            $reviewer_id = (int) ($reviewer['id'] ?? 0);
            if ($reviewer_id > 0) {
                $this->set_reviewer_panel_head($reviewer_id, false);
            }
        }
    }

    public function set_reviewer_panel_head(int $reviewer_id, bool $is_head): void
    {
        $this->wpdb->update(
            $this->reviewers_table,
            ['is_panel_head' => $is_head ? 1 : 0],
            ['id' => $reviewer_id],
            ['%d'],
            ['%d']
        );
    }

    public function is_panel_head_for_user(int $panel_id, int $user_id): bool
    {
        if ($panel_id <= 0 || $user_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->reviewers_table}
             WHERE panel_id = %d AND user_id = %d AND is_panel_head = 1",
            $panel_id,
            $user_id
        );
        $row = $this->wpdb->get_row($sql, 'ARRAY_A');

        return is_array($row);
    }

    /**
     * @param list<array{panel?: string, reviewer_name?: string, name?: string, email?: string, weight?: float|int|string}> $rows
     * @return array{
     *     imported: int,
     *     updated: int,
     *     failed: int,
     *     cleared: int,
     *     errors: list<array{row: int, email: string, message: string}>
     * }
     */
    public function import_reviewers(int $session_id, array $rows, string $import_mode = 'append'): array
    {
        $result = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'cleared' => 0,
            'errors' => [],
        ];

        if ($import_mode === 'replace') {
            $result['cleared'] = $this->delete_reviewers_for_session($session_id);
        }

        $expanded = self::expand_import_rows($rows);
        $file_email_panels = [];
        /** @var array<int, int> $import_panel_heads panel_id => reviewer_id (last wins) */
        $import_panel_heads = [];
        foreach ($expanded as $index => $row) {
            $line = self::import_row_csv_line($row, $index);
            $panel_ref = trim((string) ($row['panel'] ?? $row['panel_number'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $name = trim((string) ($row['reviewer_name'] ?? $row['name'] ?? ''));

            if ($panel_ref === '') {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'email' => $email,
                    'message' => 'Panel name or number is required.',
                ];
                continue;
            }

            if ($email === '' && $name === '') {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'email' => '',
                    'message' => 'Email or reviewer name is required.',
                ];
                continue;
            }

            $panel = $this->resolve_panel_ref($session_id, $panel_ref);
            if ($panel === null) {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $line,
                    'email' => $email,
                    'message' => 'Panel not found in this project.',
                ];
                continue;
            }

            $panel_id = (int) $panel['id'];
            $weight = $row['weight'] ?? 1;
            $payload = [
                'name' => $name,
                'email' => $email,
                'weight' => $weight,
            ];

            if ($email !== '') {
                if (
                    isset($file_email_panels[$email])
                    && $file_email_panels[$email] !== $panel_ref
                ) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $line,
                        'email' => $email,
                        'message' => sprintf(
                            'This email is already assigned to panel "%s" elsewhere in this file. Each reviewer can belong to only one panel per project.',
                            $file_email_panels[$email]
                        ),
                    ];
                    continue;
                }

                if (!isset($file_email_panels[$email])) {
                    $file_email_panels[$email] = $panel_ref;
                }

                $existing = $this->find_reviewer_by_email_in_session($session_id, $email);
                if ($existing !== null) {
                    $reviewer_id = (int) ($existing['id'] ?? 0);
                    $existing_panel_id = (int) ($existing['panel_id'] ?? 0);
                    if (
                        $import_mode === 'append'
                        && $existing_panel_id === $panel_id
                    ) {
                        $update = [];
                        if ($name !== '') {
                            $update['name'] = $name;
                        }
                        if (array_key_exists('weight', $row)) {
                            $update['weight'] = $weight;
                        }
                        if ($update !== []) {
                            $this->update_reviewer($reviewer_id, $update);
                            $result['updated']++;
                        }
                        if (self::is_truthy_panel_coordinator_flag($row) && $email !== '') {
                            $import_panel_heads[$panel_id] = $reviewer_id;
                        }
                        continue;
                    }

                    $update = ['panel_id' => $panel_id];
                    if ($name !== '') {
                        $update['name'] = $name;
                    }
                    if (array_key_exists('weight', $row)) {
                        $update['weight'] = $weight;
                    }
                    $this->update_reviewer($reviewer_id, $update);
                    $result['updated']++;
                    if (self::is_truthy_panel_coordinator_flag($row) && $email !== '') {
                        $import_panel_heads[$panel_id] = $reviewer_id;
                    }
                    continue;
                }
            }

            $new_id = $this->add_reviewer($panel_id, $payload);
            if ($new_id > 0) {
                $result['imported']++;
                if (self::is_truthy_panel_coordinator_flag($row) && $email !== '') {
                    $import_panel_heads[$panel_id] = $new_id;
                }
            }
        }

        $head_service = new \ProjectReviews\Services\PanelHeadService($this);
        foreach ($import_panel_heads as $panel_id => $reviewer_id) {
            $head_service->set_session_panel_head($reviewer_id, true);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function is_truthy_panel_coordinator_flag(array $row): bool
    {
        $raw = $row['panel_coordinator'] ?? $row['panel_coordinator_flag'] ?? '';
        $value = strtolower(trim((string) $raw));

        return in_array($value, ['1', 'yes', 'true', 'y'], true);
    }

    /**
     * Expand wide-format CSV rows (panel + reviewer_1, reviewer_1_email, …) into one row per reviewer.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function expand_import_rows(array $rows): array
    {
        $expanded = [];

        foreach ($rows as $source_index => $row) {
            $csv_row = self::csv_row_for_source($row, (int) $source_index);
            $panel_ref = trim((string) ($row['panel'] ?? $row['panel_number'] ?? ''));

            if (self::row_has_wide_reviewer_slots($row)) {
                foreach (self::expand_wide_row($row, $panel_ref) as $slot_row) {
                    $slot_row['_csv_row'] = $csv_row;
                    $expanded[] = $slot_row;
                }
                continue;
            }

            $long_name = trim((string) ($row['reviewer_name'] ?? $row['name'] ?? ''));
            $long_email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($long_name !== '' || $long_email !== '') {
                $expanded[] = [
                    'panel' => $panel_ref,
                    'reviewer_name' => $long_name,
                    'email' => $long_email,
                    'weight' => $row['weight'] ?? 1,
                    '_csv_row' => $csv_row,
                ];
                continue;
            }

            if ($panel_ref !== '') {
                $row['_csv_row'] = $csv_row;
                $expanded[] = $row;
            }
        }

        return $expanded;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function csv_row_for_source(array $row, int $source_index): int
    {
        if (isset($row['_csv_row']) && (int) $row['_csv_row'] > 0) {
            return (int) $row['_csv_row'];
        }

        return $source_index + 2;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function import_row_csv_line(array $row, int $expanded_index): int
    {
        if (isset($row['_csv_row']) && (int) $row['_csv_row'] > 0) {
            return (int) $row['_csv_row'];
        }

        return $expanded_index + 2;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function row_has_wide_reviewer_slots(array $row): bool
    {
        foreach ($row as $key => $value) {
            $normalized = strtolower(str_replace(' ', '_', (string) $key));
            if (!preg_match('/^reviewer_(\d+)$/', $normalized, $matches)) {
                continue;
            }

            $slot = (int) $matches[1];
            $name = trim((string) $value);
            $email = self::row_value_for_suffix($row, 'reviewer_' . $slot . '_email');

            if ($name !== '' || $email !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private static function expand_wide_row(array $row, string $panel_ref): array
    {
        $slots = [];

        foreach ($row as $key => $value) {
            $normalized = strtolower(str_replace(' ', '_', (string) $key));
            if (!preg_match('/^reviewer_(\d+)$/', $normalized, $matches)) {
                continue;
            }

            $slot = (int) $matches[1];
            $name = trim((string) $value);
            $email = self::row_value_for_suffix($row, 'reviewer_' . $slot . '_email');

            if ($name === '' && $email === '') {
                continue;
            }

            $weight_raw = self::row_value_for_suffix($row, 'reviewer_' . $slot . '_weight');
            $slots[$slot] = [
                'panel' => $panel_ref,
                'reviewer_name' => $name,
                'email' => strtolower($email),
                'weight' => $weight_raw !== '' ? $weight_raw : 1,
            ];
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function row_value_for_suffix(array $row, string $suffix): string
    {
        $suffix = strtolower(str_replace(' ', '_', $suffix));

        foreach ($row as $key => $value) {
            $normalized = strtolower(str_replace(' ', '_', (string) $key));
            if ($normalized === $suffix) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve_panel_ref(int $session_id, string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        $by_name = $this->find_by_name($session_id, $ref);
        if ($by_name !== null) {
            return $by_name;
        }

        if (ctype_digit($ref)) {
            $panels = $this->list_by_session($session_id);
            $index = (int) $ref - 1;
            if ($index >= 0 && isset($panels[$index])) {
                return $panels[$index];
            }
        }

        return null;
    }
}
