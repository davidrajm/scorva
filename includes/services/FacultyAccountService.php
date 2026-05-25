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
                false,
                null,
                null,
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

            if ($email === '' || $emp_id === '') {
                $result['skipped']++;
                continue;
            }

            $existing = $this->find_user_by_emp_id($emp_id);
            $provisioned = $this->provision->provision_reviewer_account(
                $email,
                (string) ($row['name'] ?? ''),
                $emp_id,
                false,
                null,
                null,
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
