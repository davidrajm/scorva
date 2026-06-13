<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Capabilities;

final class FacultyAccountService
{
    private ReviewerProvisionService $provision;

    private FacultyBridgeService $bridge;

    private AuditService $audit;

    public function __construct(
        ?ReviewerProvisionService $provision = null,
        ?FacultyBridgeService $bridge = null,
        ?AuditService $audit = null
    ) {
        $this->provision = $provision ?? new ReviewerProvisionService();
        $this->bridge = $bridge ?? new FacultyBridgeService();
        $this->audit = $audit ?? new AuditService();
    }

    /**
     * @return array{
     *     accounts: list<array<string, mixed>>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     total_pages: int
     * }
     */
    public function list_accounts(?string $search = null, int $page = 1, int $per_page = 20): array
    {
        $page = max(1, $page);
        $per_page = max(1, min(500, $per_page));
        $search = $search !== null ? trim($search) : '';
        $needle = $search !== '' ? strtolower($search) : '';

        $accounts = [];
        if (!function_exists('get_users')) {
            return [
                'accounts' => [],
                'page' => $page,
                'per_page' => $per_page,
                'total' => 0,
                'total_pages' => 0,
            ];
        }

        $users = get_users([
            'role' => Capabilities::ROLE_REVIEWER,
            'number' => 500,
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);

        foreach ($users as $user) {
            if (!is_object($user)) {
                continue;
            }

            $account = $this->format_account($user);
            if ($account === null) {
                continue;
            }

            if ($needle !== '' && !$this->matches_search($account, $needle)) {
                continue;
            }

            $accounts[] = $account;
        }

        if ($needle !== '') {
            foreach ($this->users_with_faculty_meta() as $user) {
                $account = $this->format_account($user);
                if ($account === null || !$this->matches_search($account, $needle)) {
                    continue;
                }

                $exists = false;
                foreach ($accounts as $existing) {
                    if ((int) $existing['user_id'] === (int) $account['user_id']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $accounts[] = $account;
                }
            }
        }

        usort(
            $accounts,
            static fn (array $a, array $b): int => strcmp(
                (string) ($b['created_at'] ?? ''),
                (string) ($a['created_at'] ?? '')
            )
        );

        $total = count($accounts);
        $offset = ($page - 1) * $per_page;
        $slice = array_slice($accounts, $offset, $per_page);

        $result = [
            'accounts' => $slice,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
        ];
        $result['directory_import'] = $this->bridge->directory_import_status();

        return $result;
    }

    /**
     * Create a single faculty reviewer account via the REST API.
     *
     * Validates email format before passing to the provisioning layer.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function provision_single(
        string $email,
        string $name,
        ?string $emp_id = null,
        string $designation = '',
        string $gender = ''
    ): array|\WP_Error {
        if (!is_email($email)) {
            return new \WP_Error(
                'pr_invalid_email',
                __('A valid email address is required.', 'scorva'),
                ['status' => 422]
            );
        }

        $result = $this->provision->provision_reviewer_account(
            $email,
            $name,
            $emp_id,
            false,
            null,
            null,
            [
                'designation' => $designation,
                'gender' => $gender,
            ]
        );

        if ($result instanceof \WP_Error) {
            return $result;
        }

        $user_id = (int) $result['user_id'];
        $user = function_exists('get_userdata') ? get_userdata($user_id) : false;

        $this->audit->log(
            'faculty_create_single',
            'system',
            0,
            null,
            json_encode(
                ['user_id' => $user_id, 'email' => $email, 'created' => $result['created']],
                JSON_THROW_ON_ERROR
            )
        );

        return [
            'user_id' => $user_id,
            'created' => (bool) $result['created'],
            'account' => $user !== false && $user !== null ? $this->format_account($user) : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function import_csv(array $rows, string $duplicate_policy = 'skip'): array
    {
        $result = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $line = $index + 1;
            $emp_id = trim((string) ($row['empId'] ?? $row['emp_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($emp_id === '') {
                $result['failed']++;
                $result['errors'][] = $this->error_row($line, $emp_id, $email, 'Employee ID is required.');
                continue;
            }

            if ($name === '') {
                $result['failed']++;
                $result['errors'][] = $this->error_row($line, $emp_id, $email, 'Name is required.');
                continue;
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['failed']++;
                $result['errors'][] = $this->error_row($line, $emp_id, $email, 'A valid email is required.');
                continue;
            }

            $existing_by_emp = $this->find_user_by_emp_id($emp_id);
            $existing_by_email = function_exists('get_user_by') ? get_user_by('email', $email) : false;

            if (
                $existing_by_emp !== null
                && $existing_by_email !== false
                && $existing_by_email !== null
                && (int) $existing_by_emp->ID !== (int) $existing_by_email->ID
            ) {
                $result['failed']++;
                $result['errors'][] = $this->error_row(
                    $line,
                    $emp_id,
                    $email,
                    'Employee ID and email match different accounts.'
                );
                continue;
            }

            $existing_user = $existing_by_emp !== null
                ? $existing_by_emp
                : ($existing_by_email !== false && $existing_by_email !== null ? $existing_by_email : null);

            if ($existing_user !== null && $duplicate_policy === 'skip') {
                $result['skipped']++;
                continue;
            }

            $provisioned = $this->provision->provision_reviewer_account(
                $email,
                $name,
                $emp_id,
                [
                    'designation' => trim((string) ($row['designation'] ?? '')),
                    'gender' => trim((string) ($row['gender'] ?? '')),
                ]
            );

            if ($provisioned instanceof \WP_Error) {
                $result['failed']++;
                $result['errors'][] = $this->error_row(
                    $line,
                    $emp_id,
                    $email,
                    $provisioned->get_error_message()
                );
                continue;
            }

            if ($existing_user !== null) {
                $result['updated']++;
            } else {
                $result['imported']++;
            }
        }

        $this->audit->log(
            'faculty_import_csv',
            'system',
            0,
            null,
            json_encode(
                [
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                ],
                JSON_THROW_ON_ERROR
            )
        );

        $result['error_csv'] = $this->build_error_csv($result['errors']);

        return $result;
    }

    /**
     * Delete a single faculty reviewer account.
     *
     * Deletion strategy: BLOCK with a clear error if the reviewer is currently
     * assigned to any active panel row in pr_panel_reviewers (matched by user_id).
     * We do NOT cascade-delete marks or panel assignments — the coordinator must
     * remove the reviewer from panels first. This prevents accidental data loss.
     *
     * @return array{deleted: true}|\WP_Error
     */
    public function delete_reviewer(int $user_id): array|\WP_Error
    {
        global $wpdb;

        if ($user_id <= 0) {
            return new \WP_Error(
                'pr_invalid_user',
                __('Invalid user ID.', 'scorva'),
                ['status' => 400]
            );
        }

        if (!function_exists('get_userdata')) {
            return new \WP_Error(
                'pr_provision_unavailable',
                __('User management is not available.', 'scorva'),
                ['status' => 500]
            );
        }

        $user = get_userdata($user_id);
        if ($user === false || $user === null) {
            return new \WP_Error(
                'pr_user_not_found',
                __('Reviewer account not found.', 'scorva'),
                ['status' => 404]
            );
        }

        // Block deletion if assigned to any active panel row.
        if (isset($wpdb)) {
            $reviewers_table = $wpdb->prefix . 'pr_panel_reviewers';
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reviewers_table} WHERE user_id = %d",
                    $user_id
                )
            );

            if ($count > 0) {
                return new \WP_Error(
                    'pr_reviewer_assigned',
                    sprintf(
                        /* translators: %d: number of panel assignments */
                        _n(
                            'This reviewer is assigned to %d panel. Remove them from all panels before deleting.',
                            'This reviewer is assigned to %d panels. Remove them from all panels before deleting.',
                            $count,
                            'scorva'
                        ),
                        $count
                    ),
                    ['status' => 409]
                );
            }
        }

        // Remove reviewer-specific meta and role, then delete the WP user.
        if (function_exists('delete_user_meta')) {
            delete_user_meta($user_id, 'pr_faculty_emp_id');
            delete_user_meta($user_id, 'pr_faculty_designation');
            delete_user_meta($user_id, 'pr_faculty_gender');
            delete_user_meta($user_id, 'pr_force_password_change');
        }

        // Remove from session_reviewers table if present.
        if (isset($wpdb)) {
            $session_reviewers_table = $wpdb->prefix . 'pr_session_reviewers';
            $wpdb->delete($session_reviewers_table, ['user_id' => $user_id], ['%d']);
        }

        if (!function_exists('wp_delete_user')) {
            return new \WP_Error(
                'pr_provision_unavailable',
                __('User deletion is not available.', 'scorva'),
                ['status' => 500]
            );
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);

        $this->audit->log(
            'faculty_delete_reviewer',
            'system',
            0,
            null,
            json_encode(['user_id' => $user_id], JSON_THROW_ON_ERROR)
        );

        return ['deleted' => true];
    }

    /**
     * Bulk-delete reviewer accounts.
     *
     * Each ID is processed independently; blocked IDs are collected in errors[]
     * rather than aborting the whole batch.
     *
     * @param list<int> $user_ids
     * @return array{deleted: int, failed: int, errors: list<array{user_id: int, message: string}>}
     */
    public function bulk_delete_reviewers(array $user_ids): array
    {
        $result = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($user_ids as $user_id) {
            $user_id = (int) $user_id;
            $outcome = $this->delete_reviewer($user_id);
            if ($outcome instanceof \WP_Error) {
                $result['failed']++;
                $result['errors'][] = [
                    'user_id' => $user_id,
                    'message' => $outcome->get_error_message(),
                ];
            } else {
                $result['deleted']++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function sync_from_directory(): array|\WP_Error
    {
        $rows = $this->bridge->list_active();
        if ($rows instanceof \WP_Error) {
            return $rows;
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $emp_id = trim((string) ($row['empId'] ?? ''));

            if ($status !== 'active') {
                $result['skipped']++;
                continue;
            }

            if ($email === '' || !is_email($email) || $emp_id === '') {
                $result['skipped']++;
                continue;
            }

            $existing = $this->find_user_by_emp_id($emp_id);
            $provisioned = $this->provision->provision_reviewer_account(
                $email,
                (string) ($row['name'] ?? ''),
                $emp_id,
                [
                    'designation' => (string) ($row['designation'] ?? ''),
                    'gender' => (string) ($row['gender'] ?? ''),
                ]
            );

            if ($provisioned instanceof \WP_Error) {
                $result['failed']++;
                continue;
            }

            if ($existing !== null || !($provisioned['created'] ?? false)) {
                $result['updated']++;
            } else {
                $result['created']++;
            }
        }

        $this->audit->log(
            'faculty_sync_directory',
            'system',
            0,
            null,
            json_encode($result, JSON_THROW_ON_ERROR)
        );

        return $result;
    }

    /**
     * @param object{ID?: int, user_email?: string, display_name?: string, user_registered?: string} $user
     * @return array<string, mixed>|null
     */
    private function format_account(object $user): ?array
    {
        $user_id = (int) ($user->ID ?? 0);
        if ($user_id <= 0) {
            return null;
        }

        $emp_id = '';
        if (function_exists('get_user_meta')) {
            $emp_id = trim((string) get_user_meta($user_id, 'pr_faculty_emp_id', true));
        }

        $has_reviewer_role = in_array(Capabilities::ROLE_REVIEWER, (array) ($user->roles ?? []), true);
        if (!$has_reviewer_role && $emp_id === '') {
            return null;
        }

        return [
            'user_id' => $user_id,
            'display_name' => (string) ($user->display_name ?? ''),
            'email' => (string) ($user->user_email ?? ''),
            'emp_id' => $emp_id,
            'linked' => true,
            'created_at' => (string) ($user->user_registered ?? ''),
        ];
    }

    /**
     * @return list<object>
     */
    private function users_with_faculty_meta(): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'meta_key' => 'pr_faculty_emp_id',
            'meta_compare' => 'EXISTS',
            'number' => 500,
        ]);

        return is_array($users) ? $users : [];
    }

    private function find_user_by_emp_id(string $emp_id): ?object
    {
        $emp_id = trim($emp_id);
        if ($emp_id === '' || !function_exists('get_users')) {
            return null;
        }

        $users = get_users([
            'meta_key' => 'pr_faculty_emp_id',
            'meta_value' => $emp_id,
            'number' => 1,
        ]);

        if (!is_array($users) || $users === []) {
            return null;
        }

        $user = $users[0];

        return is_object($user) ? $user : null;
    }

    /**
     * @param array<string, mixed> $account
     */
    private function matches_search(array $account, string $needle): bool
    {
        foreach (['display_name', 'email', 'emp_id'] as $field) {
            if (str_contains(strtolower((string) ($account[$field] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{row: int, emp_id: string, email: string, message: string}
     */
    private function error_row(int $row, string $emp_id, string $email, string $message): array
    {
        return [
            'row' => $row,
            'emp_id' => $emp_id,
            'email' => $email,
            'message' => $message,
        ];
    }

    /**
     * @param list<array{row: int, emp_id: string, email: string, message: string}> $errors
     */
    private function build_error_csv(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $lines = ['row,emp_id,email,error'];
        foreach ($errors as $error) {
            $lines[] = sprintf(
                '%d,%s,%s,%s',
                (int) $error['row'],
                $this->csv_escape((string) $error['emp_id']),
                $this->csv_escape((string) $error['email']),
                $this->csv_escape((string) $error['message'])
            );
        }

        return implode("\n", $lines);
    }

    private function csv_escape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
