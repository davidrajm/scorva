<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Emails\SessionClosedEmail;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;

/**
 * Closing a project locks its status; the reviewer portal refuses
 * token logins for closed projects, so no account teardown is needed.
 */
final class SessionCloseService
{
    private object $wpdb;

    private SessionRepository $sessions;

    private AuditService $audit;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('SessionCloseService requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->sessions = new SessionRepository($wpdb);
        $this->audit = new AuditService($wpdb);
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   session?: array<string, mixed>
     * }
     */
    public function close(int $session_id, ?int $actor_user_id = null): array
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return ['ok' => false, 'error' => 'session_not_found'];
        }

        if (($session['status'] ?? '') === SessionRepository::STATUS_CLOSED) {
            return ['ok' => false, 'error' => 'session_already_closed'];
        }

        if ($actor_user_id === null) {
            $actor_user_id = function_exists('get_current_user_id')
                ? (int) get_current_user_id()
                : 0;
        }

        $this->sessions->update($session_id, ['status' => SessionRepository::STATUS_CLOSED]);

        $this->audit->log(
            'session_closed',
            'session',
            $session_id,
            (string) ($session['status'] ?? ''),
            SessionRepository::STATUS_CLOSED,
            $actor_user_id
        );

        $updated = $this->sessions->find_by_id($session_id);
        if ($updated !== null && PluginSettings::notify_session_closed()) {
            SessionClosedEmail::send_for_session($updated);
        }

        return [
            'ok' => true,
            'session' => $updated ?? $session,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   session?: array<string, mixed>
     * }
     */
    public function reopen(int $session_id, ?int $actor_user_id = null): array
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return ['ok' => false, 'error' => 'session_not_found'];
        }

        if (($session['status'] ?? '') !== SessionRepository::STATUS_CLOSED) {
            return ['ok' => false, 'error' => 'session_not_closed'];
        }

        if ($actor_user_id === null) {
            $actor_user_id = function_exists('get_current_user_id')
                ? (int) get_current_user_id()
                : 0;
        }

        $restored_status = $this->resolve_reopen_status($session_id);
        $this->sessions->update($session_id, ['status' => $restored_status]);

        $this->audit->log(
            'session_reopened',
            'session',
            $session_id,
            SessionRepository::STATUS_CLOSED,
            $restored_status,
            $actor_user_id
        );

        $updated = $this->sessions->find_by_id($session_id);

        return [
            'ok' => true,
            'session' => $updated ?? $session,
        ];
    }

    /**
     * @return array{status: string, open_marks: int, credentialed_reviewers: int}|null
     */
    public function close_preview(int $session_id): ?array
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return null;
        }

        $marks = new MarkService(
            $this->sessions,
            null,
            null,
            new MarkRepository($this->wpdb),
        );

        $credentialed = 0;
        foreach ((new PanelRepository($this->wpdb))->list_reviewers_for_session($session_id) as $reviewer) {
            if (
                trim((string) ($reviewer['token'] ?? '')) !== ''
                && trim((string) ($reviewer['password_hash'] ?? '')) !== ''
            ) {
                ++$credentialed;
            }
        }

        return [
            'status' => (string) ($session['status'] ?? SessionRepository::STATUS_DRAFT),
            'open_marks' => $marks->count_open_marks($session_id),
            'credentialed_reviewers' => $credentialed,
        ];
    }

    private function resolve_reopen_status(int $session_id): string
    {
        $audit = $this->audit->list_for_session($session_id, 1, 500);
        foreach ($audit['items'] as $row) {
            if (($row['action'] ?? '') !== 'session_closed') {
                continue;
            }

            $old = (string) ($row['old_value'] ?? '');
            if (in_array($old, [SessionRepository::STATUS_DRAFT, SessionRepository::STATUS_ACTIVE], true)) {
                return $old;
            }

            break;
        }

        return SessionRepository::STATUS_ACTIVE;
    }
}
