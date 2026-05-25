<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\FieldDefinitionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class Rest_Students
{
    public static function register_routes(): void
    {
        $upload_cap = Rest_Auth::require_cap(PR_CAP_UPLOAD_STUDENTS);
        $read_cap = Rest_Auth::with_rest_nonce($upload_cap);
        $write_cap = Rest_Auth::with_rest_nonce($upload_cap);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/students',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_students'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_student'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/students/import',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'import_students'],
                'permission_callback' => $write_cap,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/students/field-schema',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_field_schema'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_field_definition'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/students/field-schema/(?P<id>\d+)',
            [
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_field_definition'],
                    'permission_callback' => $write_cap,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_field_definition'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/students/(?P<id>\d+)',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_student'],
                    'permission_callback' => $read_cap,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_student'],
                    'permission_callback' => $write_cap,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_student'],
                    'permission_callback' => $write_cap,
                ],
            ]
        );
    }

    /**
     * @return array{students: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_students(\WP_REST_Request $request): array|\WP_Error
    {
        $search = $request->get_param('search');
        $search = is_string($search) ? trim($search) : null;
        if ($search === '') {
            $search = null;
        }

        $repository = new StudentRepository();
        $students = array_map(
            [self::class, 'format_student'],
            $repository->list_all($search)
        );

        return ['students' => $students];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_student(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new StudentRepository();
        $student = $repository->find_by_id($id);

        if ($student === null) {
            return new \WP_Error(
                'pr_student_not_found',
                __('Student not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        return self::format_student($student);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_student(\WP_REST_Request $request): array|\WP_Error
    {
        $data = self::parse_student_body($request);
        if ($data instanceof \WP_Error) {
            return $data;
        }

        $repository = new StudentRepository();
        if ($repository->reg_no_exists($data['reg_no'])) {
            return new \WP_Error(
                'pr_duplicate_reg_no',
                __('A student with this registration number already exists.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $id = $repository->insert($data);
        $student = $repository->find_by_id($id);

        return self::format_student($student ?? $data);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_student(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new StudentRepository();
        $existing = $repository->find_by_id($id);

        if ($existing === null) {
            return new \WP_Error(
                'pr_student_not_found',
                __('Student not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $data = self::parse_student_body($request, false);
        if ($data instanceof \WP_Error) {
            return $data;
        }

        if (isset($data['reg_no']) && $repository->reg_no_exists($data['reg_no'], $id)) {
            return new \WP_Error(
                'pr_duplicate_reg_no',
                __('A student with this registration number already exists.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $repository->update($id, $data);
        $student = $repository->find_by_id($id);

        return self::format_student($student ?? $existing);
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_student(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new StudentRepository();

        if ($repository->find_by_id($id) === null) {
            return new \WP_Error(
                'pr_student_not_found',
                __('Student not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $repository->delete($id);

        return ['deleted' => true];
    }

    /**
     * @return array{fields: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_field_schema(): array
    {
        $repository = new FieldDefinitionRepository();

        return [
            'fields' => array_map(
                [self::class, 'format_field_definition'],
                $repository->list_all()
            ),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_field_definition(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $field_key = trim((string) ($body['field_key'] ?? ''));
        if ($field_key === '') {
            return new \WP_Error(
                'pr_invalid_field',
                __('Field key is required.', 'project-reviews'),
                ['status' => 400]
            );
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $field_key)) {
            return new \WP_Error(
                'pr_invalid_field',
                __('Field key must start with a letter and contain only lowercase letters, numbers, and underscores.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $repository = new FieldDefinitionRepository();
        if ($repository->find_by_field_key($field_key) !== null) {
            return new \WP_Error(
                'pr_duplicate_field',
                __('A field with this key already exists.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $id = $repository->insert([
            'field_key' => $field_key,
            'label' => trim((string) ($body['label'] ?? $field_key)),
            'field_type' => (string) ($body['field_type'] ?? 'text'),
            'sort_order' => (int) ($body['sort_order'] ?? 0),
        ]);

        $field = $repository->find_by_id($id);

        return self::format_field_definition($field ?? ['field_key' => $field_key]);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_field_definition(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new FieldDefinitionRepository();
        $existing = $repository->find_by_id($id);

        if ($existing === null) {
            return new \WP_Error(
                'pr_field_not_found',
                __('Field definition not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        if (isset($body['field_key'])) {
            $field_key = trim((string) $body['field_key']);
            if ($field_key === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $field_key)) {
                return new \WP_Error(
                    'pr_invalid_field',
                    __('Field key must start with a letter and contain only lowercase letters, numbers, and underscores.', 'project-reviews'),
                    ['status' => 400]
                );
            }

            $duplicate = $repository->find_by_field_key($field_key);
            if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
                return new \WP_Error(
                    'pr_duplicate_field',
                    __('A field with this key already exists.', 'project-reviews'),
                    ['status' => 409]
                );
            }
        }

        $repository->update($id, $body);
        $field = $repository->find_by_id($id);

        return self::format_field_definition($field ?? $existing);
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_field_definition(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $repository = new FieldDefinitionRepository();

        if ($repository->find_by_id($id) === null) {
            return new \WP_Error(
                'pr_field_not_found',
                __('Field definition not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $repository->delete($id);

        return ['deleted' => true];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function import_students(\WP_REST_Request $request): array|\WP_Error
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

        $field_keys = self::custom_field_keys();
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entry = [
                'reg_no' => (string) ($row['reg_no'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'program' => (string) ($row['program'] ?? ''),
                'batch' => (string) ($row['batch'] ?? ''),
            ];
            $meta = [];
            foreach ($field_keys as $key) {
                if (array_key_exists($key, $row)) {
                    $meta[$key] = (string) $row[$key];
                }
            }
            if ($meta !== []) {
                $entry['meta'] = $meta;
            }
            $normalized[] = $entry;
        }

        $repository = new StudentRepository();
        $result = $repository->import_rows($normalized, $policy);
        $result['error_csv'] = self::build_error_csv($result['errors']);

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function custom_field_keys(): array
    {
        $repository = new FieldDefinitionRepository();
        $keys = [];
        foreach ($repository->list_all() as $field) {
            $keys[] = (string) $field['field_key'];
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $student
     * @return array<string, mixed>
     */
    private static function format_student(array $student): array
    {
        return [
            'id' => (int) ($student['id'] ?? 0),
            'reg_no' => (string) ($student['reg_no'] ?? ''),
            'name' => (string) ($student['name'] ?? ''),
            'program' => (string) ($student['program'] ?? ''),
            'batch' => (string) ($student['batch'] ?? ''),
            'meta' => isset($student['meta']) && is_array($student['meta']) ? $student['meta'] : [],
            'created_at' => (string) ($student['created_at'] ?? ''),
            'updated_at' => (string) ($student['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private static function format_field_definition(array $field): array
    {
        return [
            'id' => (int) ($field['id'] ?? 0),
            'field_key' => (string) ($field['field_key'] ?? ''),
            'label' => (string) ($field['label'] ?? ''),
            'field_type' => (string) ($field['field_type'] ?? 'text'),
            'sort_order' => (int) ($field['sort_order'] ?? 0),
        ];
    }

    /**
     * @param list<array{row: int, reg_no: string, message: string}> $errors
     */
    private static function build_error_csv(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $lines = ['row,reg_no,error'];
        foreach ($errors as $error) {
            $lines[] = sprintf(
                '%d,%s,%s',
                (int) $error['row'],
                self::csv_escape((string) $error['reg_no']),
                self::csv_escape((string) $error['message'])
            );
        }

        return implode("\n", $lines);
    }

    private static function csv_escape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private static function parse_student_body(\WP_REST_Request $request, bool $require_all = true): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $reg_no = array_key_exists('reg_no', $body)
            ? trim((string) $body['reg_no'])
            : ($require_all ? '' : null);
        $name = array_key_exists('name', $body)
            ? trim((string) $body['name'])
            : ($require_all ? '' : null);

        if ($require_all || $reg_no !== null) {
            if ($reg_no === null || $reg_no === '') {
                return new \WP_Error(
                    'pr_invalid_student',
                    __('Registration number is required.', 'project-reviews'),
                    ['status' => 400]
                );
            }
        }

        if ($require_all || $name !== null) {
            if ($name === null || $name === '') {
                return new \WP_Error(
                    'pr_invalid_student',
                    __('Name is required.', 'project-reviews'),
                    ['status' => 400]
                );
            }
        }

        $data = [];
        if ($reg_no !== null) {
            $data['reg_no'] = $reg_no;
        }
        if ($name !== null) {
            $data['name'] = $name;
        }
        if (array_key_exists('program', $body)) {
            $data['program'] = trim((string) $body['program']);
        }
        if (array_key_exists('batch', $body)) {
            $data['batch'] = trim((string) $body['batch']);
        }
        if (isset($body['meta']) && is_array($body['meta'])) {
            $meta = [];
            foreach ($body['meta'] as $key => $value) {
                $meta[(string) $key] = (string) $value;
            }
            $data['meta'] = $meta;
        }

        return $data;
    }
}
