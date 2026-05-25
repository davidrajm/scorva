<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Emails\SessionClosedEmail;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\SessionRepository;

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
     *   session?: array<string, mixed>,
     *   disabled_user_ids?: list<int>
     * }
     */
    public function close(int $session_id, bool $also_disable_coordinators = false, ?int $actor_user_id = null): array
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

        $disabled = [];
        foreach ($this->list_session_reviewers($session_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $provisioned = !empty($row['provisioned_for_session']);
            $is_coordinator_capable = $this->user_has_cap($user_id, PR_CAP_MANAGE_SESSIONS);

            if ($provisioned && ! $is_coordinator_capable) {
                $this->disable_user($user_id, $session_id);
                $disabled[] = $user_id;
                $this->audit->log(
                    'account_disabled',
                    'session',
                    $session_id,
                    null,
                    (string) $user_id,
                    $actor_user_id
                );
                continue;
            }

            if ($provisioned && $is_coordinator_capable && $also_disable_coordinators) {
                $this->disable_user($user_id, $session_id);
                $disabled[] = $user_id;
                $this->audit->log(
                    'account_disabled',
                    'session',
                    $session_id,
                    null,
                    (string) $user_id,
                    $actor_user_id
                );
                continue;
            }

            if ($also_disable_coordinators && $is_coordinator_capable) {
                $this->disable_user($user_id, $session_id);
                $disabled[] = $user_id;
                $this->audit->log(
                    'account_disabled',
                    'session',
                    $session_id,
                    null,
                    (string) $user_id,
                    $actor_user_id
                );
            }
        }

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
            SessionClosedEmail::send_for_session($updated, $disabled);
        }

        return [
            'ok' => true,
            'session' => $updated ?? $session,
            'disabled_user_ids' => $disabled,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   session?: array<string, mixed>,
     *   reenabled_user_ids?: list<int>
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

        $reenabled = [];
        foreach ($this->list_disabled_session_reviewers($session_id) as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $this->enable_user($user_id, $session_id);
            $reenabled[] = $user_id;
            $this->audit->log(
                'account_enabled',
                'session',
                $session_id,
                null,
                (string) $user_id,
                $actor_user_id
            );
        }

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
            'reenabled_user_ids' => $reenabled,
        ];
    }

    /**
     * @return array{status: string, open_marks: int, provisioned_users: int, disabled_accounts: int}|null
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

        $provisioned = 0;
        foreach ($this->list_session_reviewers($session_id) as $row) {
            if (!empty($row['provisioned_for_session'])) {
                ++$provisioned;
            }
        }

        $status = (string) ($session['status'] ?? SessionRepository::STATUS_DRAFT);

        return [
            'status' => $status,
            'open_marks' => $marks->count_open_marks($session_id),
            'provisioned_users' => $provisioned,
            'disabled_accounts' => $status === SessionRepository::STATUS_CLOSED
                ? $this->count_disabled_accounts($session_id)
                : 0,
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

    private function count_disabled_accounts(int $session_id): int
    {
        return count($this->list_disabled_session_reviewers($session_id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list_disabled_session_reviewers(int $session_id): array
    {
        return array_values(
            array_filter(
                $this->list_session_reviewers($session_id),
                static fn (array $row): bool => !empty($row['disabled_at'])
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list_session_reviewers(int $session_id): array
    {
        $table = $this->wpdb->prefix . 'pr_session_reviewers';
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %d",
            $session_id
        );
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');

        return is_array($rows) ? $rows : [];
    }

    private function disable_user(int $user_id, int $session_id): void
    {
        $reviewers_table = $this->wpdb->prefix . 'pr_session_reviewers';
        $this->wpdb->update(
            $reviewers_table,
            ['disabled_at' => gmdate('Y-m-d H:i:s')],
            ['session_id' => $session_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if (function_exists('update_user_meta')) {
            update_user_meta($user_id, 'pr_account_disabled', '1');
        }
    }

    private function enable_user(int $user_id, int $session_id): void
    {
        $reviewers_table = $this->wpdb->prefix . 'pr_session_reviewers';
        $this->wpdb->update(
            $reviewers_table,
            ['disabled_at' => null],
            ['session_id' => $session_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        SessionReviewerAccountMeta::clear_account_disabled_meta_if_unused($this->wpdb, $user_id);
    }

    private function user_has_cap(int $user_id, string $cap): bool
    {
        if (!function_exists('user_can')) {
            return false;
        }

        return user_can($user_id, $cap);
    }
}
