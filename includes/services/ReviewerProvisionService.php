<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Emails\ReviewerCredentialsEmail;
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
     * Generate (or refresh) token-portal credentials for one panel reviewer.
     * The token is created once and kept stable; the password is regenerated
     * on every call. Pass $send_email = false to generate without emailing
     * (coordinator regenerate flow — they see and optionally forward creds).
     *
     * @return array{
     *     reviewer_id: int,
     *     token_created: bool,
     *     email_sent: bool,
     *     send_failed: bool,
     *     credentials_sent_at: string|null,
     *     portal_url: string,
     *     portal_password: string,
     *     password?: string,
     *     token?: string
     * }|\WP_Error
     */
    public function generate_reviewer_credentials(
        int $session_id,
        int $reviewer_id,
        string $audit_action = 'generate_reviewer_credentials',
        bool $send_email = true
    ): array|\WP_Error {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found.', 'scorva'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id((int) $reviewer['panel_id']);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found in this project.', 'scorva'), ['status' => 404]);
        }

        $email = strtolower(trim((string) ($reviewer['email'] ?? '')));
        if ($email === '' && $send_email) {
            return new \WP_Error(
                'pr_reviewer_missing_email',
                __('Email is required before sending credentials.', 'scorva'),
                ['status' => 400]
            );
        }

        $tokens = new TokenService();
        $token = trim((string) ($reviewer['token'] ?? ''));
        $token_created = false;
        if (!$tokens->is_valid_token_format($token)) {
            $token = $tokens->generate_token();
            $token_created = true;
        }

        $password = $tokens->generate_password();
        $portal_url = PluginSettings::portal_url_with_token($token);

        $email_sent = false;
        if ($send_email && $email !== '') {
            $email_sent = ReviewerCredentialsEmail::send(
                $email,
                trim((string) ($reviewer['name'] ?? '')),
                $password,
                $portal_url,
                (string) ($session['title'] ?? '')
            );
        }

        $sent_at = null;
        if ($email_sent) {
            $sent_at = function_exists('current_time')
                ? (string) current_time('mysql')
                : gmdate('Y-m-d H:i:s');
        }

        $update = [
            'token' => $token,
            'password_hash' => $tokens->hash_password($password),
            'password_encrypted' => $tokens->encrypt_password($password),
        ];
        if ($sent_at !== null) {
            $update['credentials_sent_at'] = $sent_at;
        }

        // Token reviewers have no WordPress account. The marking pipeline
        // (assignments, marks, weights, scores) keys on a numeric reviewer
        // identity in the user_id columns, so the roster row id doubles as
        // that identity.
        $needs_identity = (int) ($reviewer['user_id'] ?? 0) <= 0;
        if ($needs_identity) {
            $update['user_id'] = $reviewer_id;
        }

        $this->panels->update_reviewer($reviewer_id, $update);

        if ($needs_identity) {
            (new ReviewAssignmentRepository())->sync_panel_reviewers_to_all_reviews($session_id);
        }

        $this->audit->log(
            $audit_action,
            'session',
            $session_id,
            null,
            json_encode(
                ['reviewer_id' => $reviewer_id, 'email_sent' => $email_sent],
                JSON_THROW_ON_ERROR
            )
        );

        $response = [
            'reviewer_id' => $reviewer_id,
            'token_created' => $token_created,
            'email_sent' => $email_sent,
            'send_failed' => $send_email && $email !== '' && !$email_sent,
            'credentials_sent_at' => $sent_at ?? ((string) ($reviewer['credentials_sent_at'] ?? '') ?: null),
            'portal_url' => $portal_url,
            'portal_password' => $password,
        ];

        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $response['password'] = $password;
            $response['token'] = $token;
        }

        return $response;
    }

    /**
     * Re-send the existing portal credentials (current token URL + stored
     * password) without regenerating. Use this when the coordinator wants
     * to resend without invalidating an existing session.
     *
     * @return array{email_sent: bool, send_failed: bool, credentials_sent_at: string|null}|\WP_Error
     */
    public function resend_current_credentials(int $session_id, int $reviewer_id): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found.', 'scorva'), ['status' => 404]);
        }

        $panel = $this->panels->find_by_id((int) $reviewer['panel_id']);
        if ($panel === null || (int) $panel['session_id'] !== $session_id) {
            return new \WP_Error('pr_reviewer_not_found', __('Reviewer not found in this project.', 'scorva'), ['status' => 404]);
        }

        $email = strtolower(trim((string) ($reviewer['email'] ?? '')));
        if ($email === '') {
            return new \WP_Error(
                'pr_reviewer_missing_email',
                __('Email is required before sending credentials.', 'scorva'),
                ['status' => 400]
            );
        }

        $token = trim((string) ($reviewer['token'] ?? ''));
        $encrypted = trim((string) ($reviewer['password_encrypted'] ?? ''));
        if ($token === '' || $encrypted === '') {
            return new \WP_Error(
                'pr_no_credentials',
                __('No credentials have been generated for this reviewer. Use "Send credentials" first.', 'scorva'),
                ['status' => 400]
            );
        }

        $password = (new TokenService())->decrypt_password($encrypted);
        if ($password === '') {
            return new \WP_Error(
                'pr_credentials_unreadable',
                __('Could not read stored credentials. Regenerate credentials for this reviewer.', 'scorva'),
                ['status' => 500]
            );
        }

        $portal_url = PluginSettings::portal_url_with_token($token);
        $email_sent = ReviewerCredentialsEmail::send(
            $email,
            trim((string) ($reviewer['name'] ?? '')),
            $password,
            $portal_url,
            (string) ($session['title'] ?? '')
        );

        $sent_at = null;
        if ($email_sent) {
            $sent_at = function_exists('current_time')
                ? (string) current_time('mysql')
                : gmdate('Y-m-d H:i:s');
            $this->panels->update_reviewer($reviewer_id, ['credentials_sent_at' => $sent_at]);
        }

        $this->audit->log(
            'resend_reviewer_credentials',
            'session',
            $session_id,
            null,
            json_encode(
                ['reviewer_id' => $reviewer_id, 'email_sent' => $email_sent],
                JSON_THROW_ON_ERROR
            )
        );

        return [
            'email_sent' => $email_sent,
            'send_failed' => !$email_sent,
            'credentials_sent_at' => $sent_at ?? ((string) ($reviewer['credentials_sent_at'] ?? '') ?: null),
        ];
    }

    /**
     * @deprecated Use resend_current_credentials() for re-sending without
     *             regenerating, or generate_reviewer_credentials() directly.
     */
    public function resend_reviewer_credentials(int $session_id, int $reviewer_id): array|\WP_Error
    {
        return $this->generate_reviewer_credentials($session_id, $reviewer_id, 'resend_reviewer_credentials');
    }

    /**
     * Send portal credentials to every panel reviewer in the session.
     * Reviewers who already received credentials are skipped unless $force.
     *
     * @return array{
     *     sent: int,
     *     skipped: int,
     *     failed: int,
     *     failed_emails: list<string>,
     *     details: list<array<string, mixed>>
     * }|\WP_Error
     */
    public function send_all_reviewer_credentials(int $session_id, bool $force = false): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $failed_emails = [];
        $details = [];

        foreach ($this->panels->list_reviewers_for_session($session_id) as $reviewer) {
            $reviewer_id = (int) ($reviewer['id'] ?? 0);
            $email = strtolower(trim((string) ($reviewer['email'] ?? '')));

            if ($email === '') {
                $skipped++;
                $details[] = [
                    'reviewer_id' => $reviewer_id,
                    'status' => 'skipped',
                    'reason' => 'missing_email',
                ];
                continue;
            }

            $already_sent = trim((string) ($reviewer['credentials_sent_at'] ?? '')) !== '';
            if ($already_sent && !$force) {
                $skipped++;
                $details[] = [
                    'reviewer_id' => $reviewer_id,
                    'email' => $email,
                    'status' => 'skipped',
                    'reason' => 'already_sent',
                ];
                continue;
            }

            $result = $this->generate_reviewer_credentials($session_id, $reviewer_id, 'bulk_send_reviewer_credentials');
            if ($result instanceof \WP_Error) {
                $failed++;
                $failed_emails[] = $email;
                $details[] = [
                    'reviewer_id' => $reviewer_id,
                    'email' => $email,
                    'status' => 'failed',
                    'message' => $result->get_error_message(),
                ];
                continue;
            }

            if (!($result['email_sent'] ?? false)) {
                $failed++;
                $failed_emails[] = $email;
                $details[] = [
                    'reviewer_id' => $reviewer_id,
                    'email' => $email,
                    'status' => 'failed',
                    'message' => __('Email could not be sent.', 'scorva'),
                ];
                continue;
            }

            $sent++;
            $details[] = [
                'reviewer_id' => $reviewer_id,
                'email' => $email,
                'status' => 'sent',
            ];
        }

        $this->audit->log(
            'bulk_send_reviewer_credentials',
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
            'failed_emails' => $failed_emails,
            'details' => $details,
        ];
    }

}
