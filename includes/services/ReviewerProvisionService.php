<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Capabilities;
use ProjectReviews\Emails\ReviewerInviteEmail;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\SessionRepository;

final class ReviewerProvisionService
{
    private SessionRepository $sessions;

    private PanelRepository $panels;

    private AuditService $audit;

    public function __construct(
        ?SessionRepository $sessions = null,
        ?PanelRepository $panels = null,
        ?AuditService $audit = null
    ) {
        $this->sessions = $sessions ?? new SessionRepository();
        $this->panels = $panels ?? new PanelRepository();
        $this->audit = $audit ?? new AuditService();
    }

    /**
     * @param array{designation?: string, gender?: string} $meta
     * @return array{
     *     user_id: int,
     *     created: bool,
     *     email_sent: bool,
     *     password?: string
     * }|\WP_Error
     */
    public function provision_reviewer_account(
        string $email,
        string $name,
        ?string $emp_id = null,
        bool $send_email = false,
        ?string $session_title = null,
        ?string $login_url = null,
        array $meta = []
    ): array|\WP_Error {
        $email = strtolower(trim($email));
        if ($email === '') {
            return new \WP_Error(
                'pr_reviewer_missing_email',
                __('Email is required.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $user_result = $this->resolve_or_create_user($email, trim($name));
        if ($user_result instanceof \WP_Error) {
            return $user_result;
        }

        $user_id = $user_result['user_id'];
        $created = $user_result['created'];
        $password = $user_result['password'];

        if ($emp_id !== null && $emp_id !== '' && function_exists('update_user_meta')) {
            update_user_meta($user_id, 'pr_faculty_emp_id', trim($emp_id));
        }

        $designation = trim((string) ($meta['designation'] ?? ''));
        if ($designation !== '' && function_exists('update_user_meta')) {
            update_user_meta($user_id, 'pr_faculty_designation', $designation);
        }

        $gender = trim((string) ($meta['gender'] ?? ''));
        if ($gender !== '' && function_exists('update_user_meta')) {
            update_user_meta($user_id, 'pr_faculty_gender', $gender);
        }

        $email_sent = false;
        if ($send_email && $password !== '') {
            $login = $login_url ?? PluginSettings::login_url_with_redirect(home_url('/reviews/mark/'));
            $email_sent = ReviewerInviteEmail::send(
                $email,
                $name,
                $password,
                $login,
                $session_title ?? ''
            );
        }

        $response = [
            'user_id' => $user_id,
            'created' => $created,
            'email_sent' => $email_sent,
        ];

        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST && $password !== '') {
            $response['password'] = $password;
        }

        return $response;
    }

    /**
     * @return array{
     *     user_id: int,
     *     provisioned: bool,
     *     created: bool,
     *     email_sent: bool,
     *     password?: string
     * }|\WP_Error
     */
    public function provision_reviewer(int $session_id, int $reviewer_id): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found.', 'project-reviews'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id((int) $reviewer['panel_id']);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found in this project.', 'project-reviews'), ['status' => 404]);
        }

        $email = strtolower(trim((string) ($reviewer['email'] ?? '')));
        if ($email === '') {
            return new \WP_Error(
                'pr_reviewer_missing_email',
                __('Email is required before provisioning.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $name = trim((string) ($reviewer['name'] ?? ''));
        $user_result = $this->resolve_or_create_user($email, $name);
        if ($user_result instanceof \WP_Error) {
            return $user_result;
        }

        $user_id = $user_result['user_id'];
        $created = $user_result['created'];
        $password = $user_result['password'];
        if ($password === '' && $created) {
            $password = function_exists('wp_generate_password')
                ? wp_generate_password(16, true, true)
                : bin2hex(random_bytes(8));
        }

        $login_url = PluginSettings::login_url_with_redirect(home_url('/reviews/'));
        $email_sent = ReviewerInviteEmail::send(
            $email,
            $name,
            $password,
            $login_url,
            (string) ($session['title'] ?? '')
        );

        $this->panels->update_reviewer($reviewer_id, ['user_id' => $user_id]);
        $session_provisioned = $this->ensure_session_reviewer($session_id, $user_id, true);
        (new ReviewAssignmentRepository())->sync_panel_reviewers_to_all_reviews($session_id);

        $this->audit->log(
            'provision_reviewer',
            'session',
            $session_id,
            null,
            json_encode(['reviewer_id' => $reviewer_id, 'user_id' => $user_id], JSON_THROW_ON_ERROR)
        );

        $response = [
            'user_id' => $user_id,
            'provisioned' => $session_provisioned,
            'created' => $created,
            'email_sent' => $email_sent,
        ];

        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST && $password !== '') {
            $response['password'] = $password;
        }

        return $response;
    }

    /**
     * @return array{
     *     sent: int,
     *     skipped: int,
     *     failed: int,
     *     details: list<array<string, mixed>>
     * }|\WP_Error
     */
    public function invite_all_session_reviewers(int $session_id): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $login_url = PluginSettings::login_url_with_redirect(home_url('/reviews/mark/'));
        $session_title = (string) ($session['title'] ?? '');

        $by_email = [];
        foreach ($this->panels->list_reviewers_for_session($session_id) as $reviewer) {
            $email = strtolower(trim((string) ($reviewer['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            if (!isset($by_email[$email])) {
                $by_email[$email] = $reviewer;
            }
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($by_email as $email => $reviewer) {
            $name = trim((string) ($reviewer['name'] ?? ''));
            $account = $this->provision_reviewer_account($email, $name, null, false);
            if ($account instanceof \WP_Error) {
                $failed++;
                $details[] = [
                    'email' => $email,
                    'status' => 'failed',
                    'message' => $account->get_error_message(),
                ];
                continue;
            }

            $user_id = (int) $account['user_id'];
            $created = (bool) ($account['created'] ?? false);
            $this->link_roster_emails_to_user($session_id, $email, $user_id);

            if (Capabilities::user_has_coordinator_workspace_access_for_user($user_id)) {
                $this->ensure_session_reviewer($session_id, $user_id, false);
                $email_sent = ReviewerInviteEmail::send_login_reminder(
                    $email,
                    $name,
                    $login_url,
                    $session_title
                );
                $skipped++;
                $details[] = [
                    'email' => $email,
                    'status' => 'skipped',
                    'reason' => 'coordinator',
                    'email_sent' => $email_sent,
                ];
                continue;
            }

            $already_provisioned = $this->is_provisioned_for_session($session_id, $user_id);
            $send_credentials = $created || $already_provisioned;

            if ($send_credentials) {
                $password = function_exists('wp_generate_password')
                    ? wp_generate_password(16, true, true)
                    : bin2hex(random_bytes(8));

                if (function_exists('wp_set_password')) {
                    wp_set_password($password, $user_id);
                }

                $this->ensure_session_reviewer($session_id, $user_id, true);
                $email_sent = ReviewerInviteEmail::send(
                    $email,
                    $name,
                    $password,
                    $login_url,
                    $session_title
                );
                $sent++;
                $details[] = [
                    'email' => $email,
                    'status' => 'sent',
                    'type' => 'credentials',
                    'email_sent' => $email_sent,
                ];
                continue;
            }

            $this->ensure_session_reviewer($session_id, $user_id, false);
            $email_sent = ReviewerInviteEmail::send_login_reminder(
                $email,
                $name,
                $login_url,
                $session_title
            );
            $sent++;
            $details[] = [
                'email' => $email,
                'status' => 'sent',
                'type' => 'reminder',
                'email_sent' => $email_sent,
            ];
        }

        (new ReviewAssignmentRepository())->sync_panel_reviewers_to_all_reviews($session_id);

        $this->audit->log(
            'bulk_invite_reviewers',
            'session',
            $session_id,
            null,
            json_encode(
                ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed],
                JSON_THROW_ON_ERROR
            )
        );

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'details' => $details,
        ];
    }

    /**
     * @return array{resent: true, email_sent: bool}|\WP_Error
     */
    public function resend_credentials(int $session_id, int $reviewer_id): array|\WP_Error
    {
        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found.', 'project-reviews'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id((int) $reviewer['panel_id']);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found in this project.', 'project-reviews'), ['status' => 404]);
        }

        $user_id = (int) ($reviewer['user_id'] ?? 0);
        if ($user_id <= 0) {
            $provisioned = $this->provision_reviewer($session_id, $reviewer_id);
            if ($provisioned instanceof \WP_Error) {
                return $provisioned;
            }

            return ['resent' => true, 'email_sent' => (bool) ($provisioned['email_sent'] ?? false)];
        }

        $email = strtolower(trim((string) ($reviewer['email'] ?? '')));
        if ($email === '') {
            return new \WP_Error(
                'pr_reviewer_missing_email',
                __('Email is required to resend credentials.', 'project-reviews'),
                ['status' => 400]
            );
        }

        if (! $this->is_provisioned_for_session($session_id, $user_id)) {
            return new \WP_Error(
                'pr_resend_not_provisioned',
                __('Credentials can only be resent for accounts provisioned by this plugin.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $password = function_exists('wp_generate_password')
            ? wp_generate_password(16, true, true)
            : bin2hex(random_bytes(8));

        if (function_exists('wp_set_password')) {
            wp_set_password($password, $user_id);
        }

        $session = $this->sessions->find_by_id($session_id);
        $login_url = PluginSettings::login_url_with_redirect(home_url('/reviews/'));
        $email_sent = ReviewerInviteEmail::send(
            $email,
            (string) ($reviewer['name'] ?? ''),
            $password,
            $login_url,
            (string) ($session['title'] ?? '')
        );

        $this->audit->log(
            'resend_credentials',
            'session',
            $session_id,
            null,
            json_encode(['reviewer_id' => $reviewer_id, 'user_id' => $user_id], JSON_THROW_ON_ERROR)
        );

        return ['resent' => true, 'email_sent' => $email_sent];
    }

    /**
     * @return array{user_id: int, linked: true}|\WP_Error
     */
    public function link_existing_user(int $session_id, int $reviewer_id, int $user_id): array|\WP_Error
    {
        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found.', 'project-reviews'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id((int) $reviewer['panel_id']);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found in this project.', 'project-reviews'), ['status' => 404]);
        }

        if ($user_id <= 0 || !function_exists('get_user_by') || get_user_by('id', $user_id) === null) {
            return new \WP_Error(
                'pr_invalid_user',
                __('WordPress user not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $this->panels->update_reviewer($reviewer_id, ['user_id' => $user_id]);
        $this->ensure_session_reviewer($session_id, $user_id, false);
        (new ReviewAssignmentRepository())->sync_panel_reviewers_to_all_reviews($session_id);

        $this->audit->log(
            'link_reviewer',
            'session',
            $session_id,
            null,
            json_encode(['reviewer_id' => $reviewer_id, 'user_id' => $user_id], JSON_THROW_ON_ERROR)
        );

        return ['user_id' => $user_id, 'linked' => true];
    }

    private function link_roster_emails_to_user(int $session_id, string $email, int $user_id): void
    {
        foreach ($this->panels->list_reviewers_for_session($session_id) as $reviewer) {
            $row_email = strtolower(trim((string) ($reviewer['email'] ?? '')));
            if ($row_email !== $email) {
                continue;
            }

            $reviewer_id = (int) ($reviewer['id'] ?? 0);
            if ($reviewer_id <= 0) {
                continue;
            }

            if ((int) ($reviewer['user_id'] ?? 0) !== $user_id) {
                $this->panels->update_reviewer($reviewer_id, ['user_id' => $user_id]);
            }
        }
    }

    /**
     * @return array{user_id: int, created: bool, password: string}|\WP_Error
     */
    private function resolve_or_create_user(string $email, string $display_name): array|\WP_Error
    {
        if (!function_exists('get_user_by')) {
            return new \WP_Error(
                'pr_provision_unavailable',
                __('User provisioning is not available.', 'project-reviews'),
                ['status' => 500]
            );
        }

        $existing = get_user_by('email', $email);
        if ($existing !== null && $existing !== false) {
            $user_id = (int) $existing->ID;
            $this->ensure_reviewer_role($user_id);
            if ($display_name !== '' && function_exists('wp_update_user')) {
                wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
            }

            return [
                'user_id' => $user_id,
                'created' => false,
                'password' => '',
            ];
        }

        if (!function_exists('wp_create_user') || !function_exists('wp_generate_password')) {
            return new \WP_Error(
                'pr_provision_unavailable',
                __('User provisioning is not available.', 'project-reviews'),
                ['status' => 500]
            );
        }

        $password = wp_generate_password(16, true, true);
        $username = sanitize_user(current(explode('@', $email)), true);
        if ($username === '') {
            $username = 'reviewer_' . wp_rand(1000, 9999);
        }

        $base = $username;
        $suffix = 1;
        while (get_user_by('login', $username) !== false) {
            $username = $base . $suffix;
            $suffix++;
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_numeric($user_id)) {
            $user_id = (int) $user_id;
        }

        if ($user_id instanceof \WP_Error) {
            return $user_id;
        }

        if ($user_id <= 0) {
            return new \WP_Error(
                'pr_provision_failed',
                __('Could not create reviewer account.', 'project-reviews'),
                ['status' => 500]
            );
        }

        if ($display_name !== '' && function_exists('wp_update_user')) {
            wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
        }

        $this->ensure_reviewer_role($user_id);

        if (function_exists('update_user_meta')) {
            update_user_meta($user_id, 'pr_force_password_change', '1');
        }

        return [
            'user_id' => $user_id,
            'created' => true,
            'password' => $password,
        ];
    }

    private function ensure_reviewer_role(int $user_id): void
    {
        if (!function_exists('get_userdata') || !function_exists('get_role')) {
            return;
        }

        $user = get_userdata($user_id);
        if ($user === null || $user === false) {
            return;
        }

        foreach (Capabilities::coordinator_caps() as $cap) {
            if ($user->has_cap($cap)) {
                return;
            }
        }

        if (function_exists('get_role') && get_role(Capabilities::ROLE_REVIEWER) !== null) {
            $user->add_role(Capabilities::ROLE_REVIEWER);
        }
    }

    private function is_provisioned_for_session(int $session_id, int $user_id): bool
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return false;
        }

        $table = $wpdb->prefix . 'pr_session_reviewers';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT provisioned_for_session FROM {$table} WHERE session_id = %d AND user_id = %d",
                $session_id,
                $user_id
            ),
            'ARRAY_A'
        );

        return is_array($row) && (int) ($row['provisioned_for_session'] ?? 0) === 1;
    }

    private function ensure_session_reviewer(int $session_id, int $user_id, bool $provisioned): bool
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return false;
        }

        $table = $wpdb->prefix . 'pr_session_reviewers';
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, provisioned_for_session FROM {$table} WHERE session_id = %d AND user_id = %d",
                $session_id,
                $user_id
            ),
            'ARRAY_A'
        );

        if (is_array($existing)) {
            if ($provisioned && (int) ($existing['provisioned_for_session'] ?? 0) !== 1) {
                $wpdb->update(
                    $table,
                    ['provisioned_for_session' => 1],
                    ['id' => (int) $existing['id']],
                    ['%d'],
                    ['%d']
                );
            }

            return false;
        }

        $wpdb->insert(
            $table,
            [
                'session_id' => $session_id,
                'user_id' => $user_id,
                'provisioned_for_session' => $provisioned ? 1 : 0,
                'disabled_at' => null,
            ],
            ['%d', '%d', '%d', '%s']
        );

        return true;
    }
}
