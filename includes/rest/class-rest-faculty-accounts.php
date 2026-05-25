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
                __('Import payload must be a JSON object.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $rows = $body['rows'] ?? null;
        if (!is_array($rows) || $rows === []) {
            return new \WP_Error(
                'pr_invalid_import',
                __('Import requires at least one row.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $policy = (string) ($body['duplicate_policy'] ?? 'skip');
        if (!in_array($policy, ['skip', 'update'], true)) {
            return new \WP_Error(
                'pr_invalid_import',
                __('Duplicate policy must be "skip" or "update".', 'project-reviews'),
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
}
