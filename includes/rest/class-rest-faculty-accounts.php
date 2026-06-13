<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\FacultyAccountService;

final class Rest_Faculty_Accounts
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ASSIGN_REVIEWERS));
        $write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ASSIGN_REVIEWERS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/faculty-accounts',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_accounts'],
                    'permission_callback' => $read,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_account'],
                    'permission_callback' => $write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'bulk_delete'],
                    'permission_callback' => $write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/faculty-accounts/import',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'import_accounts'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/faculty-accounts/sync-directory',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'sync_directory'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/faculty-accounts/(?P<id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'delete_account'],
                'permission_callback' => $write,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function list_accounts(\WP_REST_Request $request): array
    {
        $search = $request->get_param('search');
        $search = is_string($search) ? trim($search) : null;
        if ($search === '') {
            $search = null;
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = max(1, min(500, (int) ($request->get_param('per_page') ?? 20)));

        return (new FacultyAccountService())->list_accounts($search, $page, $per_page);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function import_accounts(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new \WP_Error(
                'pr_invalid_import',
                __('Import payload must be a JSON object.', 'scorva'),
                ['status' => 400]
            );
        }

        $rows = $body['rows'] ?? null;
        if (!is_array($rows) || $rows === []) {
            return new \WP_Error(
                'pr_invalid_import',
                __('Import requires at least one row.', 'scorva'),
                ['status' => 400]
            );
        }

        $policy = (string) ($body['duplicate_policy'] ?? 'skip');
        if (!in_array($policy, ['skip', 'update'], true)) {
            return new \WP_Error(
                'pr_invalid_import',
                __('Duplicate policy must be "skip" or "update".', 'scorva'),
                ['status' => 400]
            );
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'empId' => (string) ($row['empId'] ?? $row['emp_id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'designation' => (string) ($row['designation'] ?? ''),
                'gender' => (string) ($row['gender'] ?? ''),
            ];
        }

        return (new FacultyAccountService())->import_csv($normalized, $policy);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function sync_directory(\WP_REST_Request $request): array|\WP_Error
    {
        unset($request);

        return (new FacultyAccountService())->sync_from_directory();
    }

    /**
     * POST /faculty-accounts — create a single faculty reviewer account.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_account(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new \WP_Error(
                'pr_invalid_request',
                __('Request body must be a JSON object.', 'scorva'),
                ['status' => 400]
            );
        }

        $name = trim((string) ($body['name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $emp_id = trim((string) ($body['emp_id'] ?? ''));
        $designation = trim((string) ($body['designation'] ?? ''));
        $gender = trim((string) ($body['gender'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors['name'] = __('Name is required.', 'scorva');
        }

        if ($email === '') {
            $errors['email'] = __('Email is required.', 'scorva');
        } elseif (!is_email($email)) {
            $errors['email'] = __('A valid email address is required.', 'scorva');
        }

        if ($errors !== []) {
            return new \WP_Error(
                'pr_validation_error',
                __('Validation failed.', 'scorva'),
                ['status' => 422, 'fields' => $errors]
            );
        }

        $result = (new FacultyAccountService())->provision_single(
            $email,
            $name,
            $emp_id !== '' ? $emp_id : null,
            $designation,
            $gender
        );

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return $result;
    }

    /**
     * DELETE /faculty-accounts/{id} — delete a single faculty reviewer account.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public static function delete_account(\WP_REST_Request $request): array|\WP_Error
    {
        $user_id = (int) $request->get_param('id');

        return (new FacultyAccountService())->delete_reviewer($user_id);
    }

    /**
     * DELETE /faculty-accounts — bulk-delete faculty reviewer accounts.
     * Body: { "ids": [1, 2, 3] }
     *
     * @return array<string, mixed>|\WP_Error
     */
    public static function bulk_delete(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new \WP_Error(
                'pr_invalid_request',
                __('Request body must be a JSON object.', 'scorva'),
                ['status' => 400]
            );
        }

        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return new \WP_Error(
                'pr_invalid_request',
                __('ids must be a non-empty array.', 'scorva'),
                ['status' => 400]
            );
        }

        $user_ids = array_map('intval', $ids);

        return (new FacultyAccountService())->bulk_delete_reviewers($user_ids);
    }
}
