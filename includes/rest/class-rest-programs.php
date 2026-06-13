<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\ProgramRepository;

final class Rest_Programs
{
    public static function register_routes(): void
    {
        $upload_cap = Rest_Auth::require_cap(PR_CAP_UPLOAD_STUDENTS);
        $read_cap = Rest_Auth::with_rest_nonce($upload_cap);
        $write_cap = Rest_Auth::with_rest_nonce($upload_cap);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/programs',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_programs'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_program'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/programs/(?P<id>\d+)',
            [
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'rename_program'],
                    'permission_callback' => $write_cap,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_program'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/programs/(?P<id>\d+)/merge',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'merge_program'],
                'permission_callback' => $write_cap,
            ]
        );
    }

    /**
     * @return array{programs: list<array<string, mixed>>}
     */
    public static function list_programs(): array
    {
        $repository = new ProgramRepository();

        return [
            'programs' => array_map(
                [self::class, 'format_program'],
                $repository->list()
            ),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_program(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $name = trim((string) ($body['name'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($name === '') {
            return new \WP_Error(
                'pr_invalid_program',
                __('Program name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $repository = new ProgramRepository();
        $result = $repository->create($name, $code);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        $program = $repository->find_by_id($result);

        return self::format_program($program ?? ['id' => $result, 'name' => $name, 'code' => $code]);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function rename_program(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $new_name = trim((string) ($body['name'] ?? ''));
        if ($new_name === '') {
            return new \WP_Error(
                'pr_invalid_program',
                __('Program name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $repository = new ProgramRepository();
        $result = $repository->rename($id, $new_name);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        $program = $repository->find_by_id($id);
        if ($program === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return self::format_program($program);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function merge_program(\WP_REST_Request $request): array|\WP_Error
    {
        $source_id = (int) $request->get_param('id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $target_id = (int) ($body['target_id'] ?? 0);
        if ($target_id <= 0) {
            return new \WP_Error(
                'pr_invalid_merge',
                __('target_id is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $repository = new ProgramRepository();
        $result = $repository->merge($source_id, $target_id);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        $target = $repository->find_by_id($target_id);
        if ($target === null) {
            return new \WP_Error(
                'pr_program_not_found',
                __('Target program not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return ['merged' => true, 'target' => self::format_program($target)];
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_program(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new ProgramRepository();
        $result = $repository->delete($id);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return ['deleted' => true];
    }

    /**
     * @param array<string, mixed> $program
     * @return array<string, mixed>
     */
    private static function format_program(array $program): array
    {
        return [
            'id' => (int) ($program['id'] ?? 0),
            'name' => (string) ($program['name'] ?? ''),
            'code' => (string) ($program['code'] ?? ''),
            'created_at' => (string) ($program['created_at'] ?? ''),
            'updated_at' => (string) ($program['updated_at'] ?? ''),
        ];
    }
}
