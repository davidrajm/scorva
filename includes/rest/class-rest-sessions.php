<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SessionDeleteService;
use ProjectReviews\Services\StudentEnrolmentService;

final class Rest_Sessions
{
    public static function register_routes(): void
    {
        $manage_read = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $manage_write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $upload_read = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_UPLOAD_STUDENTS));
        $upload_write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_UPLOAD_STUDENTS));
        $panel_write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_PANELS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_sessions'],
                    'permission_callback' => $manage_read,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_session'],
                    'permission_callback' => $manage_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_session'],
                    'permission_callback' => $manage_read,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_session'],
                    'permission_callback' => $manage_write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_session'],
                    'permission_callback' => $manage_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/students',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_enrolled_students'],
                    'permission_callback' => $upload_read,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'enrol_students'],
                    'permission_callback' => $upload_write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'remove_all_enrolled_students'],
                    'permission_callback' => $upload_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/students/(?P<student_id>\d+)',
            [
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_enrolled_student'],
                    'permission_callback' => $upload_write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'remove_enrolled_student'],
                    'permission_callback' => $upload_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/enrol',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'import_enrolment'],
                'permission_callback' => $upload_write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panels',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_panels'],
                    'permission_callback' => $panel_write,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'create_panel'],
                    'permission_callback' => $panel_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panels/(?P<panel_id>\d+)',
            [
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_panel'],
                    'permission_callback' => $panel_write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_panel'],
                    'permission_callback' => $panel_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/wizard-state',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'wizard_state'],
                'permission_callback' => $manage_read,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>|\WP_Error
     */
    public static function list_sessions(\WP_REST_Request $request): array|\WP_Error
    {
        $status = $request->get_param('status');
        $status = is_string($status) ? trim($status) : null;
        if ($status === '') {
            $status = null;
        }

        if ($status !== null && !in_array($status, SessionRepository::VALID_STATUSES, true)) {
            return new \WP_Error(
                'pr_invalid_status',
                __('Invalid project status filter.', 'scorva'),
                ['status' => 400]
            );
        }

        $repository = new SessionRepository();
        $sessions = $repository->list_all($status);
        $session_ids = array_map(
            static fn (array $session): int => (int) ($session['id'] ?? 0),
            $sessions
        );
        $enrolled_counts = $repository->count_enrolled_for_sessions($session_ids);

        return array_map(
            static function (array $session) use ($enrolled_counts): array {
                $session_id = (int) ($session['id'] ?? 0);

                return self::format_session(
                    $session,
                    $enrolled_counts[$session_id] ?? 0
                );
            },
            $sessions
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_session(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            return new \WP_Error(
                'pr_invalid_session',
                __('Project title is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $repository = new SessionRepository();
        $id = $repository->create(['title' => $title]);

        $reviews = new ReviewRepository();
        if ($reviews->count_for_session($id) === 0) {
            $review_id = $reviews->create($id, [
                'label' => 'Review 1',
                'sort_order' => 0,
                'marking_active' => false,
            ]);
            if ($review_id <= 0) {
                return new \WP_Error(
                    'pr_review_create_failed',
                    sprintf(
                        /* translators: %s: application display name */
                        __('Project was created but the first review round could not be initialized. Try deactivating and reactivating the %s plugin, then create the project again.', 'scorva'),
                        PluginSettings::app_display_name()
                    ),
                    ['status' => 500]
                );
            }
        }

        $session = $repository->find_by_id($id);
        $formatted = self::format_session(
            $session ?? ['id' => $id, 'title' => $title, 'status' => SessionRepository::STATUS_DRAFT],
            0
        );

        $student_ids = $body['student_ids'] ?? null;
        if (is_array($student_ids) && $student_ids !== []) {
            $enrolment = $repository->enrol_students_bulk($id, $student_ids, new StudentRepository());
            $formatted['enrolled_count'] = $repository->count_enrolled($id);
            $formatted['enrolment'] = $enrolment;
        }

        return $formatted;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_session(\WP_REST_Request $request): array|\WP_Error
    {
        $session = self::require_session((int) $request->get_param('id'));
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $repository = new SessionRepository();

        return self::format_session(
            $session,
            $repository->count_enrolled((int) $session['id'])
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_session(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $existing = self::require_session($id);
        if ($existing instanceof \WP_Error) {
            return $existing;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $data = [];
        if (array_key_exists('title', $body)) {
            $title = trim((string) $body['title']);
            if ($title === '') {
                return new \WP_Error(
                    'pr_invalid_session',
                    __('Project title is required.', 'scorva'),
                    ['status' => 400]
                );
            }
            $data['title'] = $title;
        }
        if (array_key_exists('status', $body)) {
            $data['status'] = (string) $body['status'];
        }

        $repository = new SessionRepository();
        $repository->update($id, $data);

        if (
            isset($data['status'])
            && (string) $data['status'] === SessionRepository::STATUS_ACTIVE
            && (string) ($existing['status'] ?? '') !== SessionRepository::STATUS_ACTIVE
        ) {
            self::sync_all_review_assignments_from_session($id);
        }

        $session = $repository->find_by_id($id);

        return self::format_session(
            $session ?? $existing,
            $repository->count_enrolled($id)
        );
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_session(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $existing = self::require_session($id);
        if ($existing instanceof \WP_Error) {
            return $existing;
        }

        $marks = new MarkRepository();
        $entered_scores = $marks->count_entered_scores_for_session($id) > 0;
        $body = self::request_body($request);
        $phrase = isset($body['confirm_label']) ? trim((string) $body['confirm_label']) : '';

        if ($entered_scores) {
            $expected = trim((string) ($existing['title'] ?? ''));
            if ($expected === '' || $phrase !== $expected) {
                return new \WP_Error(
                    'pr_session_delete_confirmation_required',
                    __('Type the exact project title to permanently delete this project and all scores.', 'scorva'),
                    ['status' => 400]
                );
            }
        }

        $result = (new SessionDeleteService())->delete($id);
        if (!($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'session_not_found');
            if ($error === 'session_not_found') {
                return new \WP_Error(
                    'pr_session_not_found',
                    __('Project not found.', 'scorva'),
                    ['status' => 404]
                );
            }

            return new \WP_Error(
                'pr_session_delete_failed',
                __('Could not delete project.', 'scorva'),
                ['status' => 500]
            );
        }

        return ['deleted' => true];
    }

    /**
     * @return array{students: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_enrolled_students(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $sessions = new SessionRepository();
        $students = new StudentRepository();
        $panels = new PanelRepository();
        $panel_map = [];
        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_map[(int) $panel['id']] = (string) $panel['name'];
        }

        $marks = new MarkRepository();
        $items = [];
        foreach ($sessions->list_enrolled($session_id) as $enrolment) {
            $student = $students->find_by_id((int) $enrolment['student_id']);
            if ($student === null) {
                continue;
            }
            $student_id = (int) $enrolment['student_id'];
            $panel_id = isset($enrolment['panel_id']) ? (int) $enrolment['panel_id'] : 0;
            $items[] = [
                'enrolment_id' => (int) $enrolment['id'],
                'student' => self::format_student_summary($student),
                'panel_id' => $panel_id > 0 ? $panel_id : null,
                'panel_name' => $panel_id > 0 ? ($panel_map[$panel_id] ?? '') : null,
                'project_title' => trim((string) ($enrolment['project_title'] ?? '')),
                'guide_emp_id' => trim((string) ($enrolment['guide_emp_id'] ?? '')),
                'guide_name' => trim((string) ($enrolment['guide_name'] ?? '')),
                'has_scores' => $marks->student_has_numeric_scores_in_session($session_id, $student_id),
            ];
        }

        return ['students' => $items];
    }

    /**
     * @return array{
     *     enrolled?: list<int>,
     *     student?: array<string, mixed>
     * }|\WP_Error
     */
    public static function enrol_students(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $reg_no = trim((string) ($body['reg_no'] ?? ''));
        if ($reg_no !== '') {
            return self::enrol_student_by_identity($session_id, $body);
        }

        $student_ids = $body['student_ids'] ?? [];
        if (!is_array($student_ids) || $student_ids === []) {
            return new \WP_Error(
                'pr_invalid_enrolment',
                __('Provide student_ids or reg_no with name for a new student.', 'scorva'),
                ['status' => 400]
            );
        }

        $sessions = new SessionRepository();
        $students = new StudentRepository();
        $enrolled = [];

        foreach ($student_ids as $student_id) {
            $student_id = (int) $student_id;
            if ($student_id <= 0 || $students->find_by_id($student_id) === null) {
                continue;
            }
            $sessions->enrol_student($session_id, $student_id);
            $enrolled[] = $student_id;
        }

        return ['enrolled' => $enrolled];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{student: array<string, mixed>}|\WP_Error
     */
    private static function enrol_student_by_identity(int $session_id, array $body): array|\WP_Error
    {
        $students = new StudentRepository();
        $resolved = $students->resolve_for_enrolment($body);
        if ($resolved instanceof \WP_Error) {
            return $resolved;
        }

        $student_id = (int) $resolved['id'];
        $sessions = new SessionRepository();

        $panel_id = null;
        if (array_key_exists('panel_id', $body)) {
            $raw_panel = $body['panel_id'];
            if ($raw_panel !== '' && $raw_panel !== false && $raw_panel !== null) {
                $panel_id = (int) $raw_panel;
                $panel = (new PanelRepository())->find_by_id($panel_id);
                if ($panel === null || (int) $panel['session_id'] !== $session_id) {
                    return new \WP_Error(
                        'pr_panel_not_found',
                        __('Panel not found in this project.', 'scorva'),
                        ['status' => 404]
                    );
                }
            }
        }

        $project_title = array_key_exists('project_title', $body)
            ? (string) ($body['project_title'] ?? '')
            : null;
        $guide_emp_id = array_key_exists('guide_emp_id', $body)
            ? (string) ($body['guide_emp_id'] ?? '')
            : null;
        $guide_name = array_key_exists('guide_name', $body)
            ? (string) ($body['guide_name'] ?? '')
            : null;

        $sessions->enrol_student(
            $session_id,
            $student_id,
            $panel_id,
            $project_title,
            $guide_emp_id,
            $guide_name
        );

        $item = self::format_enrolled_row($session_id, $student_id);
        if ($item === null) {
            return new \WP_Error(
                'pr_enrolment_failed',
                __('Could not enrol student.', 'scorva'),
                ['status' => 500]
            );
        }

        return ['student' => $item];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function format_enrolled_row(int $session_id, int $student_id): ?array
    {
        $sessions = new SessionRepository();
        $enrolment = $sessions->find_enrolment($session_id, $student_id);
        if ($enrolment === null) {
            return null;
        }

        $student = (new StudentRepository())->find_by_id($student_id);
        if ($student === null) {
            return null;
        }

        $panel_id = isset($enrolment['panel_id']) ? (int) $enrolment['panel_id'] : 0;
        $panel_name = '';
        if ($panel_id > 0) {
            $panel = (new PanelRepository())->find_by_id($panel_id);
            $panel_name = $panel !== null ? (string) ($panel['name'] ?? '') : '';
        }

        return [
            'enrolment_id' => (int) $enrolment['id'],
            'student' => self::format_student_summary($student),
            'panel_id' => $panel_id > 0 ? $panel_id : null,
            'panel_name' => $panel_name,
            'project_title' => trim((string) ($enrolment['project_title'] ?? '')),
            'guide_emp_id' => trim((string) ($enrolment['guide_emp_id'] ?? '')),
            'guide_name' => trim((string) ($enrolment['guide_name'] ?? '')),
            'has_scores' => (new MarkRepository())->student_has_numeric_scores_in_session(
                $session_id,
                $student_id
            ),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_enrolled_student(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $student_id = (int) $request->get_param('student_id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $sessions = new SessionRepository();
        $enrolment = $sessions->find_enrolment($session_id, $student_id);
        if ($enrolment === null) {
            return new \WP_Error(
                'pr_enrolment_not_found',
                __('Student is not enrolled in this project.', 'scorva'),
                ['status' => 404]
            );
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        if (array_key_exists('panel_id', $body)) {
            $panel_id = $body['panel_id'];
            $panel_id = $panel_id === '' || $panel_id === false || $panel_id === null
                ? null
                : (int) $panel_id;
            if ($panel_id !== null) {
                $panel = (new PanelRepository())->find_by_id($panel_id);
                if ($panel === null || (int) $panel['session_id'] !== $session_id) {
                    return new \WP_Error(
                        'pr_panel_not_found',
                        __('Panel not found in this project.', 'scorva'),
                        ['status' => 404]
                    );
                }
            }
            $current_panel_id = isset($enrolment['panel_id']) && $enrolment['panel_id'] !== null
                ? (int) $enrolment['panel_id']
                : null;
            if ($panel_id !== $current_panel_id) {
                $has_scores = (new MarkRepository())->student_has_numeric_scores_in_session($session_id, $student_id);
                if ($has_scores) {
                    return new \WP_Error(
                        'pr_panel_change_blocked',
                        __('This student has scores recorded. To move them to a different panel, go to the Review Assignments step, select the review round, and use the reassignment action there.', 'scorva'),
                        ['status' => 409]
                    );
                }
            }
            $sessions->assign_panel($session_id, $student_id, $panel_id);
        }

        if (array_key_exists('project_title', $body)) {
            $sessions->update_project_title(
                $session_id,
                $student_id,
                (string) ($body['project_title'] ?? '')
            );
        }

        if (array_key_exists('guide_emp_id', $body) || array_key_exists('guide_name', $body)) {
            $sessions->update_guide(
                $session_id,
                $student_id,
                array_key_exists('guide_emp_id', $body)
                    ? (string) ($body['guide_emp_id'] ?? '')
                    : null,
                array_key_exists('guide_name', $body)
                    ? (string) ($body['guide_name'] ?? '')
                    : null
            );
        }

        return ['updated' => true];
    }

    /**
     * @return array{removed: true, registry_deleted?: bool}|\WP_Error
     */
    public static function remove_enrolled_student(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $student_id = (int) $request->get_param('student_id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $result = (new StudentEnrolmentService())->remove_from_project($session_id, $student_id, false);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        return ['removed' => true];
    }

    /**
     * @return array{
     *     removed: int,
     *     registry_deleted: int,
     *     skipped_has_scores: int
     * }|\WP_Error
     */
    public static function remove_all_enrolled_students(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $body = self::request_body($request);
        $phrase = isset($body['confirm_with_scores'])
            ? trim((string) $body['confirm_with_scores'])
            : '';
        $allow_with_scores = $phrase === StudentEnrolmentService::CONFIRM_WITH_SCORES_PHRASE;

        return (new StudentEnrolmentService())->remove_all_from_project($session_id, $allow_with_scores);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function import_enrolment(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

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

        $repository = new SessionRepository();

        return $repository->import_enrolment($session_id, $rows, new StudentRepository());
    }

    /**
     * @return array{panels: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_panels(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $panels = new PanelRepository();
        $sessions = new SessionRepository();
        $items = [];
        foreach ($panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) $panel['id'];
            $student_count = $sessions->count_students_for_panel($session_id, $panel_id);
            $items[] = self::format_panel($panel, $student_count);
        }

        return ['panels' => $items];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function create_panel(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            return new \WP_Error(
                'pr_invalid_panel',
                __('Panel name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $panels = new PanelRepository();
        $existing = $panels->find_by_name($session_id, $name);
        if ($existing !== null) {
            return new \WP_Error(
                'pr_duplicate_panel',
                __('A panel with this name already exists.', 'scorva'),
                ['status' => 409]
            );
        }

        $id = $panels->create($session_id, $name);
        $panel = $panels->find_by_id($id);

        return self::format_panel_for_session(
            $session_id,
            $panel ?? ['id' => $id, 'name' => $name, 'session_id' => $session_id]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_panel(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $panel_id = (int) $request->get_param('panel_id');
        $panel = self::require_panel($session_id, $panel_id);
        if ($panel instanceof \WP_Error) {
            return $panel;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        if (array_key_exists('name', $body)) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                return new \WP_Error(
                    'pr_invalid_panel',
                    __('Panel name is required.', 'scorva'),
                    ['status' => 400]
                );
            }

            $current_name = trim((string) ($panel['name'] ?? ''));
            if ($name !== $current_name) {
                $panels = new PanelRepository();
                $existing = $panels->find_by_name($session_id, $name);
                if ($existing !== null && (int) ($existing['id'] ?? 0) !== $panel_id) {
                    return new \WP_Error(
                        'pr_duplicate_panel',
                        __('A panel with this name already exists.', 'scorva'),
                        ['status' => 409]
                    );
                }
            }
        }

        $panels = new PanelRepository();
        $panels->update($panel_id, $body);
        $updated = $panels->find_by_id($panel_id);

        return self::format_panel_for_session($session_id, $updated ?? $panel);
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_panel(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $panel_id = (int) $request->get_param('panel_id');
        $panel = self::require_panel($session_id, $panel_id);
        if ($panel instanceof \WP_Error) {
            return $panel;
        }

        $student_count = (new SessionRepository())->count_students_for_panel($session_id, $panel_id);
        if ($student_count > 0) {
            return new \WP_Error(
                'pr_panel_has_students',
                __('Cannot remove a panel while students are assigned. Reassign students first.', 'scorva'),
                ['status' => 409]
            );
        }

        (new PanelRepository())->delete($panel_id);

        return ['deleted' => true];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function wizard_state(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = self::require_session($session_id);
        if ($session instanceof \WP_Error) {
            return $session;
        }

        $sessions = new SessionRepository();
        $reviews = new ReviewRepository();
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository();
        $enrolled = $sessions->count_enrolled($session_id);
        $unassigned = $sessions->count_unassigned($session_id);
        $review_count = $reviews->count_for_session($session_id);
        $review_unassigned = $assignments->count_unassigned_all_reviews($session_id);

        $panels_ready = $enrolled > 0 && $unassigned === 0;
        $reviewers_ready = $panels_ready && $review_count > 0;
        $assignments_complete = $reviewers_ready && $review_unassigned === 0;

        $has_rubric_criteria = false;
        if ($review_count > 0) {
            $has_rubric_criteria = true;
            foreach ($reviews->list_for_session($session_id) as $review) {
                if (count($reviews->list_criteria((int) ($review['id'] ?? 0))) === 0) {
                    $has_rubric_criteria = false;
                    break;
                }
            }
        }

        $reviews_and_assignments_gate = $reviewers_ready && $has_rubric_criteria;

        return [
            'review_count' => $review_count,
            'enrolled_count' => $enrolled,
            'unassigned_count' => $unassigned,
            'review_assignment_unassigned' => $review_unassigned,
            'assignments_complete' => $assignments_complete,
            'can_advance_to_students' => true,
            'can_advance_to_panels' => $enrolled > 0,
            'can_advance_to_reviewers' => $panels_ready,
            'can_advance_to_rubrics' => $reviewers_ready,
            'can_advance_to_reviews' => $reviews_and_assignments_gate,
            'can_advance_to_assignments' => $reviews_and_assignments_gate,
        ];
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private static function format_session(array $session, ?int $enrolled_count = null): array
    {
        $session_id = (int) ($session['id'] ?? 0);

        return [
            'id' => $session_id,
            'title' => (string) ($session['title'] ?? ''),
            'status' => (string) ($session['status'] ?? SessionRepository::STATUS_DRAFT),
            'created_at' => (string) ($session['created_at'] ?? ''),
            'updated_at' => (string) ($session['updated_at'] ?? ''),
            'enrolled_count' => $enrolled_count ?? (int) ($session['enrolled_count'] ?? 0),
            'progress' => null,
            'has_entered_scores' => (new MarkRepository())->count_entered_scores_for_session($session_id) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function request_body(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $panel
     * @return array<string, mixed>
     */
    private static function format_panel_for_session(int $session_id, array $panel): array
    {
        $panel_id = (int) ($panel['id'] ?? 0);
        $student_count = (new SessionRepository())->count_students_for_panel($session_id, $panel_id);

        return self::format_panel($panel, $student_count);
    }

    /**
     * @param array<string, mixed> $panel
     * @return array<string, mixed>
     */
    private static function format_panel(array $panel, int $student_count): array
    {
        return [
            'id' => (int) ($panel['id'] ?? 0),
            'session_id' => (int) ($panel['session_id'] ?? 0),
            'name' => (string) ($panel['name'] ?? ''),
            'student_count' => $student_count,
            'deletable' => $student_count === 0,
        ];
    }

    /**
     * @param array<string, mixed> $student
     * @return array<string, mixed>
     */
    private static function format_student_summary(array $student): array
    {
        return [
            'id' => (int) ($student['id'] ?? 0),
            'reg_no' => (string) ($student['reg_no'] ?? ''),
            'name' => (string) ($student['name'] ?? ''),
            'program' => (string) ($student['program'] ?? ''),
            'batch' => (string) ($student['batch'] ?? ''),
        ];
    }

    private static function sync_all_review_assignments_from_session(int $session_id): void
    {
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository();
        $reviews = new \ProjectReviews\Repositories\ReviewRepository();
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id <= 0) {
                continue;
            }
            $assignments->ensure_assignments_from_session($review_id, $session_id);
        }
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private static function require_session(int $id): array|\WP_Error
    {
        $session = (new SessionRepository())->find_by_id($id);
        if ($session === null) {
            return new \WP_Error(
                'pr_session_not_found',
                __('Project not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return $session;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private static function require_panel(int $session_id, int $panel_id): array|\WP_Error
    {
        $panel = (new PanelRepository())->find_by_id($panel_id);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error(
                'pr_panel_not_found',
                __('Panel not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return $panel;
    }
}
