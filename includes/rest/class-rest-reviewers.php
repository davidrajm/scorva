<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\ReviewerProvisionService;

final class Rest_Reviewers
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ASSIGN_REVIEWERS));
        $write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ASSIGN_REVIEWERS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reviewers',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_reviewers'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panels/(?P<panel_id>\d+)/reviewers',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'list_panel_reviewers'],
                    'permission_callback' => $read,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'add_panel_reviewer'],
                    'permission_callback' => $write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panels/(?P<panel_id>\d+)/reviewers/(?P<reviewer_id>\d+)',
            [
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_panel_reviewer'],
                    'permission_callback' => $write,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'delete_panel_reviewer'],
                    'permission_callback' => $write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reviewers/import',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'import_reviewers'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reviewers/(?P<reviewer_id>\d+)/provision',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'provision_reviewer'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reviewers/(?P<reviewer_id>\d+)/resend-credentials',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'resend_credentials'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reviewers/(?P<reviewer_id>\d+)/link-user',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'link_user'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/invite-reviewers',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'invite_reviewers'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/users/search',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'search_users'],
                'permission_callback' => $write,
            ]
        );
    }

    /**
     * @return array{reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_reviewers(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session_check = self::require_session($session_id);
        if ($session_check instanceof \WP_Error) {
            return $session_check;
        }

        $panels = new PanelRepository();
        $items = array_map(
            static fn (array $row): array => self::format_reviewer($row, $session_id),
            $panels->list_reviewers_for_session($session_id)
        );

        return ['reviewers' => $items];
    }

    /**
     * @return array{reviewers: list<array<string, mixed>>}|\WP_Error
     */
    public static function list_panel_reviewers(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $panel_id = (int) $request->get_param('panel_id');
        $panel = self::require_panel($session_id, $panel_id);
        if ($panel instanceof \WP_Error) {
            return $panel;
        }

        $panels = new PanelRepository();

        return [
            'reviewers' => array_map(
                static fn (array $row): array => self::format_reviewer(
                    $row + [
                        'panel_id' => $panel_id,
                        'panel_name' => (string) ($panel['name'] ?? ''),
                    ],
                    $session_id
                ),
                $panels->list_reviewers($panel_id)
            ),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function add_panel_reviewer(\WP_REST_Request $request): array|\WP_Error
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

        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        if ($email === '' && $name === '') {
            return new \WP_Error(
                'pr_invalid_reviewer',
                __('Email or name is required.', 'scorva'),
                ['status' => 400]
            );
        }

        $panels = new PanelRepository();
        if ($email !== '') {
            $existing = $panels->find_reviewer_by_email_in_session($session_id, $email);
            if ($existing !== null) {
                return new \WP_Error(
                    'pr_reviewer_email_in_session',
                    __('This email is already assigned to a panel in this project.', 'scorva'),
                    ['status' => 409]
                );
            }
        }

        $id = $panels->add_reviewer($panel_id, [
            'email' => $email,
            'name' => $name,
            'weight' => $body['weight'] ?? 1,
        ]);
        if ($id <= 0) {
            return new \WP_Error(
                'pr_reviewer_insert_failed',
                __('Could not save reviewer.', 'scorva'),
                ['status' => 500]
            );
        }

        $reviewer = $panels->find_reviewer($id);
        $formatted = self::format_reviewer(
            is_array($reviewer) ? $reviewer : ['id' => $id, 'panel_id' => $panel_id],
            $session_id
        );
        $formatted['panel_id'] = $panel_id;
        $formatted['panel_name'] = (string) ($panel['name'] ?? '');

        return $formatted;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_panel_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $panel_id = (int) $request->get_param('panel_id');
        $reviewer_id = (int) $request->get_param('reviewer_id');
        $reviewer = self::require_reviewer_in_session($session_id, $reviewer_id);
        if ($reviewer instanceof \WP_Error) {
            return $reviewer;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        if (array_key_exists('panel_id', $body)) {
            $target_panel_id = (int) $body['panel_id'];
            if ($target_panel_id > 0) {
                $target = self::require_panel($session_id, $target_panel_id);
                if ($target instanceof \WP_Error) {
                    return $target;
                }
            }
        } elseif ($panel_id > 0 && (int) ($reviewer['panel_id'] ?? 0) !== $panel_id) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found on this panel.', 'scorva'),
                ['status' => 404]
            );
        }

        $panels = new PanelRepository();
        $email = array_key_exists('email', $body)
            ? strtolower(trim((string) $body['email']))
            : strtolower(trim((string) ($reviewer['email'] ?? '')));
        if ($email !== '') {
            $existing = $panels->find_reviewer_by_email_in_session($session_id, $email);
            if ($existing !== null && (int) ($existing['id'] ?? 0) !== $reviewer_id) {
                return new \WP_Error(
                    'pr_reviewer_email_in_session',
                    __('This email is already assigned to a panel in this project.', 'scorva'),
                    ['status' => 409]
                );
            }
        }

        if (array_key_exists('is_panel_head', $body)) {
            $head_result = (new \ProjectReviews\Services\PanelHeadService())->set_session_panel_head(
                $reviewer_id,
                (bool) $body['is_panel_head']
            );
            if ($head_result instanceof \WP_Error) {
                return $head_result;
            }
            unset($body['is_panel_head']);
        }

        if ($body !== []) {
            $panels->update_reviewer($reviewer_id, $body);
        }
        $updated = (new PanelRepository())->find_reviewer($reviewer_id);
        if (!is_array($updated)) {
            return self::format_reviewer($reviewer, $session_id);
        }

        $panel = (new PanelRepository())->find_by_id((int) ($updated['panel_id'] ?? 0));

        return self::format_reviewer(
            $updated + [
                'panel_name' => is_array($panel) ? (string) ($panel['name'] ?? '') : '',
            ],
            $session_id
        );
    }

    /**
     * @return array{deleted: true}|\WP_Error
     */
    public static function delete_panel_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $panel_id = (int) $request->get_param('panel_id');
        $reviewer_id = (int) $request->get_param('reviewer_id');
        $reviewer = self::require_reviewer_in_session($session_id, $reviewer_id);
        if ($reviewer instanceof \WP_Error) {
            return $reviewer;
        }

        if ($panel_id > 0 && (int) ($reviewer['panel_id'] ?? 0) !== $panel_id) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found on this panel.', 'scorva'),
                ['status' => 404]
            );
        }

        (new PanelRepository())->delete_reviewer($reviewer_id);

        return ['deleted' => true];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function import_reviewers(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session_check = self::require_session($session_id);
        if ($session_check instanceof \WP_Error) {
            return $session_check;
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

        $import_mode = (string) ($body['import_mode'] ?? 'append');
        if (!in_array($import_mode, ['append', 'replace'], true)) {
            return new \WP_Error(
                'pr_invalid_import',
                __('import_mode must be append or replace.', 'scorva'),
                ['status' => 400]
            );
        }

        return (new PanelRepository())->import_reviewers($session_id, $rows, $import_mode);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function provision_reviewer(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $reviewer_id = (int) $request->get_param('reviewer_id');

        return (new ReviewerProvisionService())->provision_reviewer($session_id, $reviewer_id);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function resend_credentials(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $reviewer_id = (int) $request->get_param('reviewer_id');

        return (new ReviewerProvisionService())->resend_credentials($session_id, $reviewer_id);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function link_user(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $reviewer_id = (int) $request->get_param('reviewer_id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $user_id = (int) ($body['user_id'] ?? 0);

        return (new ReviewerProvisionService())->link_existing_user($session_id, $reviewer_id, $user_id);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function invite_reviewers(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session_check = self::require_session($session_id);
        if ($session_check instanceof \WP_Error) {
            return $session_check;
        }

        return (new ReviewerProvisionService())->invite_all_session_reviewers($session_id);
    }

    /**
     * @return array{users: list<array{id: int, display_name: string, email: string}>}|\WP_Error
     */
    public static function search_users(\WP_REST_Request $request): array|\WP_Error
    {
        $query = trim((string) ($request->get_param('q') ?? ''));
        if ($query === '' || !function_exists('get_users')) {
            return ['users' => []];
        }

        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20,
        ]);

        $items = [];
        foreach ($users as $user) {
            if (!is_object($user)) {
                continue;
            }
            $items[] = [
                'id' => (int) ($user->ID ?? 0),
                'display_name' => (string) ($user->display_name ?? ''),
                'email' => (string) ($user->user_email ?? ''),
            ];
        }

        return ['users' => $items];
    }

    /**
     * @param array<string, mixed> $reviewer
     * @return array<string, mixed>
     */
    private static function format_reviewer(array $reviewer, int $session_id = 0): array
    {
        $user_id = isset($reviewer['user_id']) ? (int) $reviewer['user_id'] : 0;
        $provisioned = false;
        if ($user_id > 0 && $session_id > 0) {
            global $wpdb;
            if (isset($wpdb)) {
                $table = $wpdb->prefix . 'pr_session_reviewers';
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT provisioned_for_session FROM {$table} WHERE session_id = %d AND user_id = %d",
                        $session_id,
                        $user_id
                    ),
                    'ARRAY_A'
                );
                $provisioned = is_array($row) && (int) ($row['provisioned_for_session'] ?? 0) === 1;
            }
        }

        return [
            'id' => (int) ($reviewer['id'] ?? 0),
            'panel_id' => (int) ($reviewer['panel_id'] ?? 0),
            'panel_name' => (string) ($reviewer['panel_name'] ?? ''),
            'name' => (string) ($reviewer['name'] ?? ''),
            'email' => (string) ($reviewer['email'] ?? ''),
            'weight' => (float) ($reviewer['weight'] ?? 1),
            'user_id' => $user_id > 0 ? $user_id : null,
            'linked' => $user_id > 0,
            'provisioned' => $provisioned,
            'is_panel_head' => (int) ($reviewer['is_panel_head'] ?? 0) === 1,
        ];
    }

    /**
     * @return true|\WP_Error
     */
    private static function require_session(int $id): true|\WP_Error
    {
        if ((new SessionRepository())->find_by_id($id) === null) {
            return new \WP_Error(
                'pr_session_not_found',
                __('Project not found.', 'scorva'),
                ['status' => 404]
            );
        }

        return true;
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

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private static function require_reviewer_in_session(int $session_id, int $reviewer_id): array|\WP_Error
    {
        $reviewer = (new PanelRepository())->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $panel = (new PanelRepository())->find_by_id((int) ($reviewer['panel_id'] ?? 0));
        if ($panel === null || (int) ($panel['session_id'] ?? 0) !== $session_id) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found.', 'scorva'),
                ['status' => 404]
            );
        }

        $reviewer['panel_name'] = (string) ($panel['name'] ?? '');

        return $reviewer;
    }
}
